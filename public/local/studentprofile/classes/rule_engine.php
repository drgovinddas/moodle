<?php
/**
 * Core Rule Engine for evaluating assignment conditions.
 *
 * @package    local_studentprofile
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studentprofile;

defined('MOODLE_INTERNAL') || die();

class rule_engine {

    /**
     * Evaluates a JSON rule tree against a set of student data.
     *
     * @param string $json_conditions
     * @param \stdClass $student_data
     * @return bool
     */
    public static function evaluate(string $json_conditions, \stdClass $student_data): bool {
        if (empty($json_conditions)) {
            return false;
        }

        $conditions = @json_decode($json_conditions, true);
        if (!$conditions || empty($conditions['rules'])) {
            return false;
        }

        return self::evaluate_group($conditions, $student_data);
    }

    /**
     * Evaluates a condition group (AND / OR).
     *
     * @param array $group
     * @param \stdClass $data
     * @return bool
     */
    private static function evaluate_group(array $group, \stdClass $data): bool {
        if (empty($group['rules'])) {
            return true; // Empty group is true by default.
        }

        $logical_op = strtoupper($group['condition'] ?? 'AND');

        foreach ($group['rules'] as $rule) {
            // Check if it's a nested group.
            if (isset($rule['condition'])) {
                $result = self::evaluate_group($rule, $data);
            } else {
                $result = self::evaluate_rule($rule, $data);
            }

            if ($logical_op === 'AND' && !$result) {
                return false;
            }
            if ($logical_op === 'OR' && $result) {
                return true;
            }
        }

        return ($logical_op === 'AND');
    }

    /**
     * Evaluates a single rule condition.
     *
     * @param array $rule
     * @param \stdClass $data
     * @return bool
     */
    private static function evaluate_rule(array $rule, \stdClass $data): bool {
        $field = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? '=';
        $expected = $rule['value'] ?? null;

        // Extract actual value from student data.
        if ($field === 'reporting_week') {
            $timestamp = !empty($data->timecreated) ? (int)$data->timecreated : 0;
            if ($timestamp <= 0) {
                return false;
            }
            $month = strtolower(date('M', $timestamp)); // e.g. jan, feb, mar
            $year  = date('Y', $timestamp);             // e.g. 2026
            $day   = (int)date('j', $timestamp);
            
            // Calculate week of month (1, 2, 3, or 4)
            if ($day <= 7) {
                $week = 1;
            } else if ($day <= 14) {
                $week = 2;
            } else if ($day <= 21) {
                $week = 3;
            } else {
                $week = 4;
            }
            
            $actual = $month . ' ' . $year . ' ' . $week . ' week';
        } else {
            $actual = property_exists($data, $field) ? $data->$field : null;
        }

        if ($actual === null) {
            return false;
        }

        // Handle numeric arrays (e.g. multi-select IN)
        if (is_array($expected)) {
            if ($operator === 'in') {
                return in_array($actual, $expected);
            } elseif ($operator === 'not_in') {
                return !in_array($actual, $expected);
            } elseif ($operator === 'between') {
                return (count($expected) == 2 && $actual >= $expected[0] && $actual <= $expected[1]);
            }
        }

        // Standard string/number operators.
        switch ($operator) {
            case 'equal':
            case '=':
                // Case-insensitive string match or numeric match.
                return (strcasecmp((string)$actual, (string)$expected) === 0);
                
            case 'not_equal':
            case '!=':
                return (strcasecmp((string)$actual, (string)$expected) !== 0);
                
            case 'in':
                return in_array($actual, (array)$expected);
                
            case 'not_in':
                return !in_array($actual, (array)$expected);
                
            case 'less':
            case '<':
                return ((float)$actual < (float)$expected);
                
            case 'less_or_equal':
            case '<=':
                return ((float)$actual <= (float)$expected);
                
            case 'greater':
            case '>':
                return ((float)$actual > (float)$expected);
                
            case 'greater_or_equal':
            case '>=':
                return ((float)$actual >= (float)$expected);
                
            case 'contains':
                return (stripos((string)$actual, (string)$expected) !== false);
                
            case 'not_contains':
                return (stripos((string)$actual, (string)$expected) === false);
                
            case 'begins_with':
            case 'starts_with':
                return (stripos((string)$actual, (string)$expected) === 0);
                
            case 'ends_with':
                $len = strlen((string)$expected);
                if ($len === 0) return true;
                return (strripos((string)$actual, (string)$expected) === strlen((string)$actual) - $len);
                
            case 'is_empty':
                return empty($actual);
                
            case 'is_not_empty':
                return !empty($actual);
                
            case 'is_null':
                return $actual === null;
                
            case 'is_not_null':
                return $actual !== null;
                
            default:
                return false;
        }
    }
}
