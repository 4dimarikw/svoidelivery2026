import assert from 'node:assert/strict';
import test from 'node:test';
import { catalogFilters } from '../../resources/js/catalog-filters.js';

function fixture(range, from, to) {
    const input = (name, value) => ({ name, value, get valueAsNumber() { return this.value === '' ? NaN : Number(this.value); } });
    const min = input(`${range}_min`, from);
    const max = input(`${range}_max`, to);
    let submitted;
    const filters = catalogFilters();
    filters.$root = {
        elements: { namedItem: (name) => [min, max].find((field) => field.name === name) ?? null },
        submit: () => { submitted = [min.value, max.value]; },
    };
    return { filters, min, max, submitted: () => submitted };
}

for (const range of ['price', 'abv', 'ibu']) {
    test(`${range}: correct the edited boundary only after finishing input`, () => {
        const { filters, min, max } = fixture(range, '10', '1');
        filters.rememberRange(max);
        assert.equal(max.value, '1');
        filters.finishRange(max);
        assert.equal(max.value, '10');
        min.value = '20';
        filters.rememberRange(min);
        filters.finishRange(min);
        assert.equal(min.value, '10');
    });

    test(`${range}: Enter and sorting correct the last edited boundary before submission`, () => {
        const { filters, min, max, submitted } = fixture(range, '10.5', '5.6');
        filters.rememberRange(min);
        filters.correctRanges();
        assert.equal(min.value, '5.6');
        max.value = '2.5';
        filters.rememberRange(max);
        filters.submitFilters();
        assert.deepEqual(submitted(), ['5.6', '5.6']);
    });

    test(`${range}: preserve valid, equal, empty and nonnumeric boundaries`, () => {
        for (const values of [['20', '25'], ['5.6', '5.6'], ['', '100'], ['100', ''], ['', ''], ['bad', '1']]) {
            const { filters, min, max } = fixture(range, ...values);
            filters.finishRange(min);
            filters.finishRange(max);
            filters.correctRanges();
            assert.deepEqual([min.value, max.value], values);
        }
    });
}

test('ABV is capped at 100 even with the other boundary empty', () => {
    for (const [from, to, expected] of [['', '101', ['', '100']], ['101', '', ['100', '']], ['200', '300', ['100', '100']]]) {
        const { filters, min, max, submitted } = fixture('abv', from, to);
        filters.finishRange(max);
        assert.deepEqual([min.value, max.value], expected);
        max.value = '101';
        filters.submitFilters();
        assert.deepEqual(submitted(), [expected[0], '100']);
    }
});

test('prices and IBU above 100 are not capped', () => {
    for (const range of ['price', 'ibu']) {
        const { filters, min, max } = fixture(range, '200', '250');
        filters.correctRanges();
        assert.deepEqual([min.value, max.value], ['200', '250']);
    }
});
