define(['jquery'], function($) {

    var init = function(degreeMap, collegeMap) {
        var typeSelect = $('#id_degreetype_select');
        var degreeSelect = $('#id_degree_select');
        var collegeSelect = $('#id_collegeid_key');
        
        if (degreeSelect.length === 0) {
            return;
        }

        // Store original degree options
        var originalDegreeOptions = degreeSelect.find('option').clone();

        // Filter degrees based on degree type (UG/PG)
        var filterDegrees = function() {
            if (typeSelect.length === 0) {
                return;
            }
            var selectedType = typeSelect.val();
            var currentDegree = degreeSelect.val();
            
            degreeSelect.empty();
            
            originalDegreeOptions.each(function() {
                var option = $(this);
                var val = option.val();
                
                if (val === '') {
                    degreeSelect.append(option.clone());
                    return;
                }
                
                if (!selectedType || !degreeMap || degreeMap[val] === selectedType) {
                    degreeSelect.append(option.clone());
                }
            });
            
            if (degreeSelect.find('option[value="' + currentDegree + '"]').length > 0) {
                degreeSelect.val(currentDegree);
            } else {
                degreeSelect.val('');
            }
        };

        // Store original college options
        var originalCollegeOptions = collegeSelect.find('option').clone();

        // Filter colleges based on selected degree (MBBS, BDS, BHMS, BPT, etc.)
        var filterColleges = function() {
            if (collegeSelect.length === 0 || !collegeMap) {
                return;
            }
            var selectedDegree = degreeSelect.val();
            var currentCollege = collegeSelect.val();

            // First pass: count matching colleges if a degree is selected
            var matchingCount = 0;
            if (selectedDegree) {
                originalCollegeOptions.each(function() {
                    var collegeId = $(this).val();
                    if (collegeId !== '' && collegeId !== '__custom__') {
                        var allowedDegrees = collegeMap[collegeId];
                        if (allowedDegrees && allowedDegrees.length > 0 && allowedDegrees.indexOf(selectedDegree) !== -1) {
                            matchingCount++;
                        }
                    }
                });
            }

            // If selectedDegree is empty or matchingCount is 0, show all colleges
            var showAll = !selectedDegree || (matchingCount === 0);

            // Re-populate options
            collegeSelect.empty();
            originalCollegeOptions.each(function() {
                var option = $(this);
                var collegeId = option.val();

                if (collegeId === '' || collegeId === '__custom__' || showAll) {
                    collegeSelect.append(option.clone());
                    return;
                }

                var allowedDegrees = collegeMap[collegeId];
                if (allowedDegrees && allowedDegrees.length > 0 && allowedDegrees.indexOf(selectedDegree) !== -1) {
                    collegeSelect.append(option.clone());
                }
            });

            // Restore selection if still present in new options
            if (collegeSelect.find('option[value="' + currentCollege + '"]').length > 0) {
                collegeSelect.val(currentCollege);
            } else {
                collegeSelect.val('');
                collegeSelect.trigger('change');
            }
        };

        if (typeSelect.length > 0) {
            typeSelect.on('change', function() {
                filterDegrees();
                filterColleges();
            });
        }
        
        degreeSelect.on('change', filterColleges);
        
        // Initial filter on page load
        filterDegrees();
        filterColleges();
    };

    return {
        init: init
    };
});
