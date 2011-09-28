<?php

interface semester_codes {
    const FALL = '1S';
    const SPRING = '2S';
    const SUMMER = '3S';
    const WINTER_INT = '1T';
    const SPRING_INT = '2T';
    const SUMMER_INT = '3T';
}

interface institution_codes {
    const LSU_SEM = 'CLSB';
    const LAW_SEM = 'LAWB';

    const LSU_FINAL = 'PSTGRD';
    const LAW_FINAL = 'LFGDF';

    const LSU_CAMPUS = '01';
    const LAW_CAMPUS = '08';

    const LSU_INST = '1590';
    const LAW_INST = '1595';
}

abstract class lsu_source implements institution_codes, semester_codes {
    /**
     * An LSU source requires these
     */
    var $serviceId;
    var $username;
    var $password;
    var $wsdl;

    function __construct($username, $password, $wsdl) {
        $this->username = $username;
        $this->password = $password;
        $this->wsdl = $wsdl;
    }

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

abstract class lsu_user_source extends lsu_source {
    var $info;

    function __construct($username, $password, $wsdl) {
        parent::__construct($username, $password, $wsdl);

        $this->info = new lsu_profile_info($username, $password, $wsdl);
    }

    public function fill($user) {
        $info = $this->info($user->idnumber);

        foreach (get_object_vars($info) as $field => $value) {
            $user->$field = $value;
        }

        return $user;
    }
}
