<?php
class custom extends def_module {
    public function cms_callMethod($method_name, $args) {
        return call_user_func_array(Array($this, $method_name), $args);
    }

    public function __call($tpl, $args) {
        $cmsController = cmsController::getInstance();
        $module = (isset($args[0])) ? $args[0] : false;
        $method = (isset($args[1])) ? $args[1] : false;
        $params = (isset($args[2])) ? $args[2] : array();
        $composite = (isset($args[3])) ? $args[3] : false;
        if($module && $cmsController->isModule($module) && $method){
            $lang = getRequest('lang');
            if(!$lang) $lang = $cmsController->getCurrentLang()->getPrefix();
            $serialize = $params;
            array_unshift($serialize, $method);
            array_unshift($serialize, $module);
            $md5 = md5(implode('/',$serialize).'/'.$tpl.'/'.$lang);
            $call_string = serialize($serialize);
            $connection = ConnectionPool::getInstance()->getConnection('core');
            $connection->query("CREATE TABLE IF NOT EXISTS  umi_relation_call_point (
                       id		VARCHAR(32)	PRIMARY KEY,
                       call_string		MEDIUMTEXT	DEFAULT NULL,
                       tpl		VARCHAR(32)	DEFAULT NULL,
                       lang		VARCHAR(8)	DEFAULT NULL
                  ) engine=innodb DEFAULT CHARSET=utf8;");
            $connection->query("DELETE FROM umi_relation_call_point WHERE id = '{$md5}'");
            $connection->query("INSERT INTO umi_relation_call_point (id, call_string, tpl, lang) VALUES('{$md5}', '{$call_string}', '{$tpl}', '{$lang}')");
            if($composite) return $md5;
            $func_res = call_user_func_array(array($cmsController->getModule($module), $method), $params);
            $func_res['callPoint'] = $md5;
            $currentTemplater = $cmsController->getCurrentTemplater();
            if ($currentElementId = $cmsController->getCurrentElementId()) {
                $currentTemplater->setScope($currentElementId);
            }
            $result = $currentTemplater->render($func_res, $tpl);
            return $result;
        }
        else{
            throw new publicException("Method " . get_class($this) . "::" . $module . " doesn't exists");
        }
    }

    public function call_point($id) {
        session_start();
        if(!$id) return false;
        $id = l_mysql_real_escape_string($id);
        $sql = "SELECT call_string,tpl,lang FROM umi_relation_call_point WHERE id = '{$id}'";
        $result = l_mysql_query($sql, true);
        list($call_string, $tpl, $lang_prefix) = mysql_fetch_row($result);
        if(!$call_string) return false;
        if(!$tpl) $tpl = 'default';
        $cmsController = cmsController::getInstance();
        $lang_id = langsCollection::getInstance()->getLangId($lang_prefix);
        $lang = langsCollection::getInstance()->getLang($lang_id);
        $cmsController->setLang($lang);
        $args = unserialize((string) $call_string);
        $module = array_shift($args);
        $method = array_shift($args);

        $res = call_user_func_array(Array($cmsController->getModule($module),$method), $args);
        $template = $cmsController->detectCurrentDesignTemplate();
        outputBuffer::contentGenerator('PHP' . ', SITE MODE');

        $config = mainConfiguration::getInstance();
        $currentTemplater = umiTemplater::create('PHP', $template->getFilePath());

        if (!is_null(getRequest('showStreamsCalls'))) {
            $currentTemplater->setEnabledCallStack(!$config->get('debug', 'callstack.disabled'));
        }

        if ($currentElementId = getRequest('currentElementId')) {
            $currentTemplater->setScope($currentElementId);
        }
        $result = $currentTemplater->render($res, $tpl);

        $buffer = outputBuffer::current();
        $buffer->contentType('text/html');
        $buffer->clear();
        $buffer->push($result);
        $buffer->end();
    }
    //TODO: Write your own macroses here

}
?>