<?php

require_once dirname(__FILE__) . '/lib.php';

// TODO: fix the stubs
class cps_semester extends cps_dao {
}

class cps_course extends cps_dao {
}

class cps_section extends cps_dao {
}

class cps_teacher extends cps_dao {
}

class cps_student extends cps_dao {
}

class cps_user extends cps_dao {

    public static function tablename() {
        return self::get_name();
    }

}
