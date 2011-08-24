<?php

abstract class lsu_source {
    const FALL = '1S';
    const SPRING = '2S';
    const SUMMER = '3S';
    const WINTER_INT = '1T';
    const SPRING_INT = '2T';
    const SUMMER_INT = '3T';

    /**
     * An LSU source requires these
     */
    var $serviceId;
    var $username;
    var $password;
    var $wsdl;

    private function build_parameters(array $params) {
        return array (
            'widget1' => $this->username,
            'widget2' => $this->password,
            'serviceId' => $this->serviceId,
            'parameters' => $params
        );
    }

    private function escape_illegals($response) {
        $convertables = array(
            '/s?&s?/' => ' &amp; ',
        );
        foreach ($convertables as $pattern => $replaced) {
            $response = preg_replace($pattern, $replaced, $response);
        }
        return $response;
    }

    private function clean_response($response) {
        $clean = $this->escape_illegals($response);

        $contents = <<<XML
<?xml version='1.0'?>
<rows>
    $clean
</rows>
XML;
        return $contents;
    }

    public function set_params(array $params) {
        foreach ($params as $key => $value) {
            $this->$key = $value;
        }

        return $this;
    }

    public function invoke($params) {
        $client = new SoapClient($this->wsdl);

        $invoke_params = $this->build_parameters($params);
        $response = $client->invoke($invoke_params)->invokeReturn;

        return new SimpleXmlElement($this->clean_response($response));
    }

    public function parse_date($date) {
        $parts = explode('-', $date);
        return mktime(0, 0, 0, $parts[1], $parts[2], $parts[0]);
    }

    public function parse_name($fullname) {
        list($lastname, $fm) = explode(',', $fullname);
        list($first, $middle) = explode(' ', $fm);

        if (strlen($first) == 1) {
            $first = $first . ' ' . $middle;
        }

        return array($lastname, $first);
    }

    public function encode_semester($semester_year, $semester_name) {

        $partial = function ($year, $name) {
            return sprintf('%d%s', $year, $name);
        };

        switch ($semester_name) {
            case 'Fall': return $partial($semester_year + 1, self::FALL);
            case 'WinterInt': return $partial($semester_year + 1, self::WINTER_INT);
            case 'Summer': return $partial($semester_year, self::SUMMER);
            case 'Spring': return $partial($semester_year, self::SPRING);
            case 'SummerInt': return $partial($semester_year, self::SUMMER_INT);
            case 'SpringInt': return $partial($semester_year, self::SPRING_INT);
        }
    }
}
