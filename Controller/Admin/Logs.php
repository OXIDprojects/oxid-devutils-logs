<?php

namespace OxidCommunity\DevutilsLogs\Controller\Admin;

use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\Eshop\Core\Registry;

class Logs extends AdminController {
    protected $_sThisTemplate = 'dev_logs.tpl';

    protected $_sExLog = null;
    protected $_sErrLog = null;

    public function init() {
        parent::init();

        $cfg = Registry::getConfig();

        $this->_sExLog = $cfg->getConfigParam('sShopDir') . 'log/oxideshop.log';
        $this->_sErrLog = ($cfg->getConfigParam('s_Dev_serverLogPath')) ? $cfg->getConfigParam('s_Dev_serverLogPath') : false;
        //$this->_sSqlLog = ($cfg->getConfigParam('bSqlLog')) ? $cfg->getConfigParam('sSqlLog') : false;
        //$this->_sMailsLog = ($cfg->getConfigParam('bMailLog')) ? $cfg->getConfigParam('sMailLog') : false;

        if (substr($this->_sErrLog, 0, 1) !== "/") $this->_sErrLog = $cfg->getConfigParam('sShopDir') . $this->_sErrLog; // relative path for webserver error log?
    }

    public function render() {
        $cfg = Registry::getConfig();

        //$this->addTplParam('exLog', $this->getExceptionLog());
        $this->addTplParam('errlog', $this->_sErrLog);
        $this->addTplParam('ip', $_SERVER['REMOTE_ADDR']);

        // var_dump("<h2>".$this->_sExLogPath."</h2>");
        // $this->getExceptionLog();
        // echo "<hr/>";
        // var_dump("<h2>".$this->_sErrorLog."</h2>");
        // $this->addTplParam('ErrorLog', $this->getErrorLog());
        // echo "<hr/>";
        // var_dump("<h2>".$this->_sDbLog."</h2>");
        // echo "<hr/>";
        // var_dump("<h2>".$this->_sMailLog."</h2>");
        // $this->addTplParam('ExceltionLog', "logs");


        return parent::render();
    }

    public function getExceptionLog() {
        $cfg = Registry::getConfig();
        $sExLog = $cfg->getConfigParam('sShopDir') . 'log/oxideshop.log';

        if (!file_exists($sExLog) || !is_readable($sExLog)) die(json_encode(['status' => "oxideshop.log does not exist or is not readable"]));

        $sData = file_get_contents($sExLog);

        $aData = explode("\n", $sData);
        $aData = array_slice($aData, -101);
        array_pop($aData); // cut last empty array element
        foreach ($aData as $key => $value) {
            $aEx = explode("Stack Trace:", trim($value));
            $aHeader = explode("[0]:", $aEx[0]);

            $iFC = preg_match("/Faulty\scomponent\s\-\-\>\s(.*)/", $aEx[1], $aFC);

            $aData[$key] = (object)array(
                "header"    => str_replace($cfg->getConfigParam("sShopDir"), "", trim($aHeader[0])),
                "subheader" => str_replace($cfg->getConfigParam("sShopDir"), "", trim($aHeader[1])) . ($iFC == 1 ? ": " . $aFC[1] : ""),
                "text"      => htmlentities(str_replace($cfg->getConfigParam("sShopDir"), "", trim($aEx[1]))),
                "full"      => $value
            );
        }

        //$time = filemtime($sExLog);
        die(json_encode(['status' => 'ok', 'log' => array_reverse($aData)]));
    }

    public function getErrorLog() {
        if (!$this->_sErrLog) return false;
        if (!file_exists($this->_sErrLog) || !is_readable($this->_sErrLog)) die(json_encode(['status' => "file does not exist or is not readable"]));

        $sData = "\n" . file_get_contents($this->_sErrLog);
        $aData = preg_split('/\n\[/', $sData);
        unset($aData[0]);

        // xdebug?
        if (function_exists('xdebug_get_code_coverage')) $this->_getXdebugErrorLog($aData);

        $aData = array_slice($aData, -300);
        foreach ($aData as $key => $value) {
            /*
            [Sat May 30 10:32:38 2015] [error] [client 79.222.227.99]
            PHP Fatal error:  Smarty error: [in dev_footer.tpl line 7]: syntax error: unrecognized tag: $module_components:default:"'lumx'" (Smarty_Compiler.class.php, line 446) in /srv/ox/bla/core/smarty/Smarty.class.php on line 1093, referer: http://ox.marat.ws/bla/admin/index.php?editlanguage=0&force_admin_sid=5fdleoj00epaoe5d75d5hi6q01&stoken=C8D0F139&&cl=navigation&item=home.tpl
            */
            preg_match('/\]\s{1}[^\[]/', $value, $matches, PREG_OFFSET_CAPTURE);
            $meta = '[' . substr($value, 0, $matches[0][1]) . ']';
            $msg = substr($value, $matches[0][1] + 2);

            preg_match_all("/\[([^\]]*)\]/", $meta, $header);
            //var_dump($header);

            //$msg = trim(str_replace(array_slice($header[0], 0, 4), '', $value));

            /*
            preg_match("/\sin\s\/(.*)\sreferer\:/", $msg, $in); // in: between " in" and " referer"
            preg_match("/\sreferer\:(.*)/", $msg, $ref); // referer: after "referer"
            $replace = [$ref[0], ' in /' . $in[1]];
            */

            $aErr = [
                "date"   => date_format((date_create($header[1][0]) ? date_create($header[1][0]) : date_create_from_format("D M d H:i:s.u Y", $header[1][0])), 'Y-m-d H:i:s'),
                "type"   => $header[1][1],
                "client" => $header[1][3],
                "msg"    => $msg,
                "full"   => $value

            ];
            $aData[$key] = (object)$aErr;

            // "in" => str_replace($cfg->getConfigParam("sShopDir"),"",substr($msg, strpos($msg, " in /")+3, strpos($msg, ", referer: "))),
        }
        //echo "<pre>";
        echo json_encode(['status' => 'ok', 'log' => array_reverse($aData)]);
        exit;
    }

    protected function _getXdebugErrorLog($aData) {
        $cfg = Registry::getConfig();
        $aLog = [];

        $i = -1;
        $logdate = '';
        foreach ($aData as $row) {
            if ($i > 30) break; // 30 lag entries are enough, i suppose
            preg_match("/\[([^\]]*)\]/", $row, $date);

            // stack trace
            if ((preg_match("/\] PHP Stack trace/", $row) > 0 || preg_match("/\] PHP\s+\d/", $row) > 0) && $date[1] == $logdate) {
                $aLog[$i]['stacktrace'][] = substr($row, strlen($date[0]));

            } else // first log line
            {
                $i++;
                $logdate = $date[1];
                preg_match("/\] PHP\s(.*):/", $row, $type);
                preg_match("/\sPHP.+\:\s+(.*)\sin\s/", $row, $header);
                preg_match("/\sin\s\/(.*)/", $row, $in);

                $aLog[$i] = [
                    'date'       => date_format(date_create($logdate), 'Y-m-d H:i:s'),
                    'type'       => $type[1],
                    'header'     => $header[1],
                    'in'         => str_replace($cfg->getConfigParam("sShopDir"), "", "/" . $in[1]),
                    'stacktrace' => [],
                    'full'       => $row
                ];
            }
        }
        echo json_encode(array_reverse($aLog));
        //echo print_r($aLog);
        exit;
    }

}
