define(['jquery'], function($) {

    var init = function(degreeMap) {
        var typeSelect = $('#id_degreetype_select');
        var degreeSelect = $('#id_degree_select');
        
        if (typeSelect.length === 0 || degreeSelect.length === 0) {
            return;
        }

        // Store original options to restore them when needed
        var originalOptions = degreeSelect.find('option').clone();

        var filterDegrees = function() {
            var selectedType = typeSelect.val();
            var currentDegree = degreeSelect.val();
            
            // Clear current options
            degreeSelect.empty();
            
            // Re-populate options based on the map
            originalOptions.each(function() {
                var option = $(this);
                var val = option.val();
                
                // Always add the "Choose..." empty option
                if (val === '') {
                    degreeSelect.append(option.clone());
                    return;
                }
                
                // Check if the degree type matches or if no type is selected
                if (!selectedType || degreeMap[val] === selectedType) {
                    degreeSelect.append(option.clone());
                }
            });
            
            // Restore previous selection if it's still available
            if (degreeSelect.find('option[value="' + currentDegree + '"]').length > 0) {
                degreeSelect.val(currentDegree);
            } else {
                degreeSelect.val('');
            }
        };

        // Listen for changes on Degree Type
        typeSelect.on('change', filterDegrees);
        
        // Initial filter on page load
        filterDegrees();
    };

    return {
        init: init
    };
});
