const ranges = ['price', 'abv', 'ibu'];

export function catalogFilters() {
    return {
        lastEdited: {},

        rangeFor(input) {
            return ranges.find((range) => input.name === `${range}_min` || input.name === `${range}_max`);
        },

        rememberRange(input) {
            const range = this.rangeFor(input);
            if (range) this.lastEdited[range] = input.name;
        },

        correctRange(range, editedName = this.lastEdited[range]) {
            const min = this.$root.elements.namedItem(`${range}_min`);
            const max = this.$root.elements.namedItem(`${range}_max`);
            if (range === 'abv') {
                for (const input of [min, max]) {
                    if (input && Number.isFinite(input.valueAsNumber) && input.valueAsNumber > 100) input.value = '100';
                }
            }
            if (!min || !max || min.value === '' || max.value === '') return;
            if (!Number.isFinite(min.valueAsNumber) || !Number.isFinite(max.valueAsNumber)) return;
            if (min.valueAsNumber <= max.valueAsNumber) return;

            if (editedName === min.name) min.value = max.value;
            else max.value = min.value;
        },

        finishRange(input) {
            const range = this.rangeFor(input);
            if (range) this.correctRange(range, input.name);
        },

        correctRanges() {
            ranges.forEach((range) => this.correctRange(range));
        },

        submitFilters() {
            // Native submit bypasses the submit event (sorting and optional auto-submit).
            this.correctRanges();
            this.$root.submit();
        },
    };
}
