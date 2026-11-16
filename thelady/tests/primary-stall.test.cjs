const assert = require("node:assert/strict");
const { readFileSync } = require("node:fs");
const { join } = require("node:path");
const test = require("node:test");
const vm = require("node:vm");

const player = readFileSync(join(__dirname, "../music.html"), "utf8");
const start = player.indexOf("function stopPrimaryStallMonitor() {");
const end = player.indexOf("function startSecondaryNoAudioTimer(", start);
assert.ok(start !== -1 && end !== -1, "Stall monitor functions must exist");
const monitor = player.slice(start, end);
const recoveryStart = player.indexOf("function resetPrimaryRecovery() {");
const recoveryEnd = player.indexOf("function startPrimaryNoAudioTimer()", recoveryStart);
assert.ok(recoveryStart !== -1 && recoveryEnd !== -1, "Recovery functions must exist");
const recovery = player.slice(recoveryStart, recoveryEnd);

function createMonitor() {
    let now = 0;
    let tick;
    const recoveries = [];
    const messages = [];
    const context = {
        primaryStallTimer: null,
        primaryMediaGeneration: 1,
        primaryPlaybackRequested: true,
        primaryRecovery: null,
        audio: { currentTime: 0, paused: false, ended: false },
        state: {
            currentUrl: "station",
            sourceType: "csv",
            series: [{ urlFileName: "station" }, { urlFileName: "next" }],
            index: 0,
            awaitingUserPlay: false
        },
        autoplayCheckbox: { checked: true },
        repeatCheckbox: { checked: false },
        secondaryPlaybackInProgress: false,
        activeSecondaryPlaylist: null,
        performance: { now: () => now },
        setInterval: callback => { tick = callback; return 1; },
        clearInterval: () => { tick = null; },
        recoverPrimaryAudio: stalled => recoveries.push(stalled),
        resetPrimaryRecovery: () => { context.primaryRecovery = null; },
        message: text => messages.push(text)
    };
    vm.createContext(context);
    vm.runInContext(monitor, context);
    context.startPrimaryStallMonitor();

    return {
        context,
        recoveries,
        messages,
        advance(ms) {
            now += ms;
            if (tick) tick();
        },
        isRunning: () => tick !== null
    };
}

test("skips a stalled item at 30 seconds, not before", () => {
    const m = createMonitor();
    m.advance(29999);
    assert.deepEqual(m.recoveries, []);
    m.advance(1);
    assert.deepEqual(m.recoveries, [true]);
    assert.match(m.messages[0], /stalled for 30 seconds/);
    assert.equal(m.isRunning(), false);
});

test("playback progress restarts the stall deadline", () => {
    const m = createMonitor();
    m.advance(29000);
    m.context.audio.currentTime = 1;
    m.advance(1000);
    m.advance(29999);
    assert.deepEqual(m.recoveries, []);
    m.advance(1);
    assert.deepEqual(m.recoveries, [true]);
});

test("pausing or changing the source stops the monitor", () => {
    for (const change of [
        c => { c.audio.paused = true; },
        c => { c.audio.ended = true; },
        c => { c.primaryMediaGeneration++; },
        c => { c.state.currentUrl = "another station"; }
    ]) {
        const m = createMonitor();
        change(m.context);
        m.advance(30000);
        assert.deepEqual(m.recoveries, []);
        assert.equal(m.isRunning(), false);
    }
});

test("disabled autoplay, repeat and secondary playback do not skip", () => {
    for (const change of [
        c => { c.autoplayCheckbox.checked = false; },
        c => { c.repeatCheckbox.checked = true; },
        c => { c.state.awaitingUserPlay = true; },
        c => { c.activeSecondaryPlaylist = {}; },
        c => { c.state.index = 1; }
    ]) {
        const m = createMonitor();
        change(m.context);
        m.advance(60000);
        assert.deepEqual(m.recoveries, []);
    }
});

test("recovery history clears only after sustained playback progress", () => {
    const m = createMonitor();
    m.context.primaryRecovery = { stalled: true };
    for (let second = 1; second <= 30; second++) {
        m.context.audio.currentTime = second;
        m.advance(1000);
    }
    assert.notEqual(m.context.primaryRecovery, null);
    m.context.audio.currentTime++;
    m.advance(1000);
    assert.equal(m.context.primaryRecovery, null);
});

test("recovery stops after every item stalls", async () => {
    const callbacks = [];
    const messages = [];
    const records = [{ urlFileName: "first" }, { urlFileName: "second" }];
    const context = {
        primaryRecovery: null,
        primaryPlaybackRequested: true,
        secondaryPlaybackInProgress: false,
        activeSecondaryPlaylist: null,
        state: { sourceType: "csv", series: records, index: 0 },
        audio: { paused: false, pause() { this.paused = true; } },
        setTimeout: callback => { callbacks.push(callback); return callbacks.length; },
        clearTimeout() {},
        stopPrimaryNoAudioTimer() {},
        stopMetadataMonitor() {},
        isBlockedRemoteHTTP: () => false,
        playRecord: async record => { context.state.currentUrl = record.urlFileName; },
        message: text => messages.push(text),
        updateDebug() {},
        console
    };
    vm.createContext(context);
    vm.runInContext(recovery, context);

    assert.equal(context.recoverPrimaryAudio(true), true);
    await callbacks.shift()();
    assert.equal(context.state.index, 1);
    assert.equal(context.primaryRecovery.stalled, true);
    assert.equal(context.recoverPrimaryAudio(true), true);
    await callbacks.shift()();
    assert.equal(context.primaryPlaybackRequested, false);
    assert.equal(context.audio.paused, true);
    assert.match(messages.at(-1), /No playable item/);
});
