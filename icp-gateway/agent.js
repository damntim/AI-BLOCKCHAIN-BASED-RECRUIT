const { HttpAgent, Actor } = require('@dfinity/agent');
const { idlFactory }       = require('./idl');
require('dotenv').config();

let _actor = null;

async function buildActor() {
    const host        = process.env.ICP_HOST      || 'http://localhost:8000';
    const canisterId  = process.env.CANISTER_ID   || '';

    if (!canisterId) {
        throw new Error('CANISTER_ID is not set in icp-gateway/.env');
    }

    const agent = new HttpAgent({ host, fetchOptions: { keepAlive: false } });

    // Required for local replica — fetches the root key that signs responses
    if (host.includes('localhost') || host.includes('127.0.0.1')) {
        await agent.fetchRootKey();
    }

    const actor = Actor.createActor(idlFactory, { agent, canisterId });
    console.log(`[ICP] Actor created — canister ${canisterId} @ ${host}`);
    return actor;
}

// Returns a cached actor, creating it on first call.
// Throws if the canister is unreachable; callers must handle the error.
async function getActor() {
    if (!_actor) {
        _actor = await buildActor();
    }
    return _actor;
}

// Reset cached actor (useful if the connection drops)
function resetActor() {
    _actor = null;
}

module.exports = { getActor, resetActor };
