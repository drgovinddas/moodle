define(['core/datafilter/filtertype'], function(Filter) {
    var BaseFilter = Filter.default || Filter;
    class StringFilter extends BaseFilter {
        get values() { return this.rawValues; }
    }
    return StringFilter;
});
