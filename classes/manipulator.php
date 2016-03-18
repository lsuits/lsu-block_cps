<?php

/**
 *
 * @package    block_cps
 * @copyright  2016 Louisiana State University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once $CFG->dirroot . '/blocks/cps/classes/exceptions/CPSException.php';
require_once $CFG->dirroot . '/blocks/cps/classes/exceptions/CPSManipulatorException.php';

abstract class cps_manipulator {

    public static function handle($component, $eventName, $data = array()) {

        // check that this component can be handled
        $handleableComponents = array(
            'enrol_ues',
            'block_ues_people'
        );

        if ( ! in_array($component, $handleableComponents))
            throw new CPSManipulatorException('CPS is trying to handle a component that is not registered as an manipulator');

        try {
            
            $manipulatorClass = self::getManipulatorClassName($component);

            return self::dispatchManipulationEvent($manipulatorClass, $eventName, $data);
            
        } catch (Exception $e) {
            throw new CPSManipulatorException('CPS could not run the ' . $eventName . ' method on the ' . $manipulatorClass);
        }
        
    }

    /**
     * Mutates the given component into a manipulator class name
     * 
     * @param  string  $component
     * @return string
     */
    private static function getManipulatorClassName($component) {

        $manipulatorClass = $component . '_manipulator';

        return $manipulatorClass;
    }

    /**
     * Handles the manipulator event
     * 
     * @param  object  $manipulatorClass  a callable cps manipulator
     * @param  string  $eventName
     * @param  array   $data
     * @return mixed
     */
    private static function dispatchManipulationEvent($manipulatorClass, $eventName, $data) {

        // require the manipulator lib
        $manipulatorClassFilename = self::getManipulatorFilename($manipulatorClass);
        
        self::requireManipulatorLib($manipulatorClassFilename);

        // get an instantiated manipulator class
        $manipulator = new $manipulatorClass();

        self::checkEventExists($manipulator, $eventName);

        // call the event and return the result
        return $manipulator->$eventName($data);
    }

    /**
     * Returns the full filename for this manipulator class
     * 
     * @param  string  $classname
     * @return string
     */
    private static function getManipulatorFilename($classname) {
        
        global $CFG;

        $filename = $CFG->dirroot . '/blocks/cps/classes/manipulators/' . $classname . '.php';

        return $filename;
    }

    /**
     * Requires the given manipulator lib file
     * 
     * @param  string  $filename
     * @return null
     */
    private static function requireManipulatorLib($filename) {

        if ( ! file_exists($filename))
            throw new CPSManipulatorException('CPS could not load the ' . $filename . ' class.');

        require_once $filename;
    }

    /**
     * Checks that the dispatched manipulator class has the specifed event method
     * 
     * @param  base_inector  $manipulator
     * @param  string        $eventName
     * @return null
     */
    private static function checkEventExists($manipulator, $eventName) {

        if ( ! method_exists($manipulator, $eventName))
            throw new CPSManipulatorException('CPS could not find the event (' . $eventName . ') within the specified manipulator');
    }

}