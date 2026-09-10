import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

function formHarness(fetch, assign = () => {}) {
    const components = new Map();
    vm.runInNewContext(readFileSync(new URL('../../resources/js/ui.js', import.meta.url), 'utf8'), {
        document: { addEventListener: (_, callback) => callback() },
        Alpine: { data: (name, factory) => components.set(name, factory) },
        FormData: class {
            constructor(form) { this.key = form.checkout_key; }
        },
        fetch,
        window: { location: { assign } },
    });
    return components.get('uiForm')({ mode: 'ajax' });
}

test('повторный submit во время отправки не создаёт второй запрос', async () => {
    let finish;
    let requests = 0;
    const form = formHarness(() => {
        requests++;
        return new Promise(resolve => { finish = resolve; });
    });
    const element = { action: '/checkout', checkout_key: 'same-key' };
    const pending = form.submit(element);
    await form.submit(element);
    assert.equal(requests, 1);
    finish({ status: 200, ok: true, headers: { get: () => '/account/orders/1' } });
    await pending;
});

test('изменение цен открывает корзину без повторной отправки', async () => {
    let destination;
    let requests = 0;
    const form = formHarness(async () => {
        requests++;
        return { status: 422, json: async () => ({ code: 'cart_prices_changed', redirect: '/cart', errors: { checkout: ['Prices changed'] } }) };
    }, url => { destination = url; });
    await form.submit({ action: '/checkout', submit() { assert.fail('Unexpected resubmit'); } });
    assert.equal(requests, 1);
    assert.equal(destination, '/cart');
    assert.equal(form.errorFor('checkout'), 'Prices changed');
});

test('сетевая ошибка сохраняет ключ при обычной отправке формы', async () => {
    let sentKey;
    let fallbackKey;
    const form = formHarness(async (_, options) => {
        sentKey = options.body.key;
        throw new Error('response lost');
    });
    const element = {
        action: '/checkout', checkout_key: 'same-key',
        submit() { fallbackKey = this.checkout_key; },
    };
    await form.submit(element);
    assert.equal(sentKey, 'same-key');
    assert.equal(fallbackKey, sentKey);
    assert.equal(form.mode, 'default');
});
