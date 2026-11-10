import assert from 'node:assert/strict';
import { test } from 'node:test';
import { confirmMessage, initConfirmForms } from '../../resources/js/confirm.js';

test('confirmMessage reads data-confirm and tolerates plain forms', () => {
    assert.equal(confirmMessage({ dataset: { confirm: 'Sure?' } }), 'Sure?');
    assert.equal(confirmMessage({ dataset: {} }), null);
    assert.equal(confirmMessage(null), null);
});

test('a declined confirmation cancels the submit, an accepted one does not', () => {
    let listener;
    const doc = { addEventListener: (_, fn) => (listener = fn) };

    const run = (answer) => {
        let prevented = false;
        initConfirmForms(doc, () => answer);
        listener({ target: { dataset: { confirm: 'Delete?' } }, preventDefault: () => (prevented = true) });

        return prevented;
    };

    assert.equal(run(false), true);
    assert.equal(run(true), false);
});
