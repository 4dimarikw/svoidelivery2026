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
        const { filters, min, max } = fixture(range, '1000', '100');
        filters.rememberRange(max);
        assert.equal(max.value, '100');
        filters.finishRange(max);
        assert.equal(max.value, '1000');
        min.value = '2000';
        filters.rememberRange(min);
        filters.finishRange(min);
        assert.equal(min.value, '1000');
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
        for (const values of [['200', '250'], ['5.6', '5.6'], ['', '100'], ['100', ''], ['', ''], ['bad', '1']]) {
            const { filters, min, max } = fixture(range, ...values);
            filters.finishRange(min);
            filters.finishRange(max);
            filters.correctRanges();
            assert.deepEqual([min.value, max.value], values);
        }
    });
}
