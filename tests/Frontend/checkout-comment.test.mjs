import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const template = readFileSync(new URL('../../resources/views/pages/cart.blade.php', import.meta.url), 'utf8');
const change = template.match(/x-on:change="([^"]*state\.comment = [^"]*)"/)[1];
const input = template.match(/x-on:input="([^"]*state\.commentEdited[^"]*)"/)[1];

test('address selection updates defaults until the user edits or clears the comment', () => {
    const scope = { state: { comment: 'Profile', commentEdited: false }, addressComments: { 1: 'Door code', 2: 'Profile' }, $el: { value: '1' } };
    vm.runInNewContext(change, scope);
    assert.equal(scope.state.comment, 'Door code');
    scope.$el.value = '2';
    vm.runInNewContext(change, scope);
    assert.equal(scope.state.comment, 'Profile');
    for (const manual of ['My instructions', '']) {
        scope.state.comment = manual;
        vm.runInNewContext(input, scope);
        scope.$el.value = '1';
        vm.runInNewContext(change, scope);
        assert.equal(scope.state.comment, manual);
    }
});
