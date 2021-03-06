<?php

/**
 * @Project NUKEVIET 4.x
 * @Author VINADES.,JSC (contact@vinades.vn)
 * @Copyright (C) 2014 VINADES.,JSC. All rights reserved
 * @License GNU/GPL version 2 or any later version
 * @Createdate 3-6-2010 0:33
 */

if (!defined('NV_IS_MOD_VOTING')) {
    die('Stop!!!');
}

$vid = $nv_Request->get_int('vid', 'get', 0);

if (empty($vid)) {
    $page_title = $module_info['site_title'];
    $key_words = $module_info['keywords'];

    $sql = 'SELECT vid, question, link, acceptcm, active_captcha, groups_view, publ_time, exp_time FROM ' . NV_PREFIXLANG . '_' . $module_data . ' WHERE act=1 ORDER BY publ_time DESC';
    $list = $nv_Cache->db($sql, 'vid', 'voting');

    $allowed = array();
    $is_update = array();

    $a = 0;
    foreach ($list as $row) {
        if ($row['exp_time'] > 0 and $row['exp_time'] < NV_CURRENTTIME) {
            $is_update[] = $row['vid'];
        } elseif ($row['publ_time'] <= NV_CURRENTTIME and nv_user_in_groups($row['groups_view'])) {
            $allowed[$a] = $row;
            ++$a;
        }
    }

    if (!empty($is_update)) {
        $is_update = implode(',', $is_update);

        $sql = 'UPDATE ' . NV_PREFIXLANG . '_' . $module_data . ' SET act=0 WHERE vid IN (' . $is_update . ')';
        $db->query($sql);

        $nv_Cache->delMod($module_name);
    }

    if (!empty($allowed)) {
        $xtpl = new XTemplate('main.tpl', NV_ROOTDIR . '/themes/' . $module_info['template'] . '/modules/' . $module_info['module_theme']);
        $xtpl->assign('LANG', \NukeViet\Core\Language::$lang_module);

        foreach ($allowed as $current_voting) {
            $voting_array = array(
                'checkss' => md5($current_voting['vid'] . NV_CHECK_SESSION),
                'accept' => (int) $current_voting['acceptcm'],
                'active_captcha' => ((int)$current_voting['active_captcha'] ? ($global_config['captcha_type'] == 2 ? 2 : 1) : 0),
                'errsm' => (int) $current_voting['acceptcm'] > 1 ? sprintf($nv_Lang->getModule('voting_warning_all'), (int) $current_voting['acceptcm']) : $nv_Lang->getModule('voting_warning_accept1'),
                'vid' => $current_voting['vid'],
                'question' => (empty($current_voting['link'])) ? $current_voting['question'] : '<a target="_blank" href="' . $current_voting['link'] . '">' . $current_voting['question'] . '</a>',
                'action' => NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&amp;' . NV_NAME_VARIABLE . '=' . $module_name,
                'langresult' => $nv_Lang->getModule('voting_result'),
                'langsubmit' => $nv_Lang->getModule('voting_hits'),
                'publtime' => nv_date('l - d/m/Y H:i', $current_voting['publ_time'])
            );
            $xtpl->assign('VOTING', $voting_array);

            $sql = 'SELECT id, vid, title, url FROM ' . NV_PREFIXLANG . '_' . $site_mods['voting']['module_data'] . '_rows WHERE vid = ' . $current_voting['vid'] . ' ORDER BY id ASC';
            $list = $nv_Cache->db($sql, '', $module_name);

            foreach ($list as $row) {
                if (!empty($row['url'])) {
                    $row['title'] = '<a target="_blank" href="' . $row['url'] . '">' . $row['title'] . '</a>';
                }
                $xtpl->assign('RESULT', $row);
                if ((int) $current_voting['acceptcm'] > 1) {
                    $xtpl->parse('main.loop.resultn');
                } else {
                    $xtpl->parse('main.loop.result1');
                }
            }

            if ($voting_array['active_captcha']) {
                if ($global_config['captcha_type'] == 2) {
                    $xtpl->assign('RECAPTCHA_ELEMENT', 'recaptcha' . nv_genpass(8));
                    $xtpl->assign('N_CAPTCHA', $nv_Lang->getGlobal('securitycode1'));
                    $xtpl->parse('main.loop.has_captcha.recaptcha');
                } else {
                    $xtpl->assign('N_CAPTCHA', $nv_Lang->getGlobal('securitycode'));
                    $xtpl->assign('CAPTCHA_REFRESH', $nv_Lang->getGlobal('captcharefresh'));
                    $xtpl->assign('GFX_WIDTH', NV_GFX_WIDTH);
                    $xtpl->assign('GFX_HEIGHT', NV_GFX_HEIGHT);
                    $xtpl->assign('SRC_CAPTCHA', NV_BASE_SITEURL . 'index.php?scaptcha=captcha&t=' . NV_CURRENTTIME);
                    $xtpl->assign('GFX_MAXLENGTH', NV_GFX_NUM);
                    $xtpl->parse('main.loop.has_captcha.basic');
                }
                $xtpl->parse('main.loop.has_captcha');
            }

            $xtpl->parse('main.loop');
        }

        $xtpl->parse('main');
        $contents = $xtpl->text('main');
    }

    include NV_ROOTDIR . '/includes/header.php';
    echo nv_site_theme($contents);
    include NV_ROOTDIR . '/includes/footer.php';
} else {
    $checkss = $nv_Request->get_title('checkss', 'get', '');
    $lid = $nv_Request->get_title('lid', 'get', '');
    $captcha = $nv_Request->get_title('captcha', 'get', '');

    if ($checkss != md5($vid . NV_CHECK_SESSION) or $vid <= 0 or $lid == '') {
        header('location:' . $global_config['site_url']);
        exit();
    }

    $sql = 'SELECT vid, question, acceptcm, active_captcha, groups_view, publ_time, exp_time FROM ' . NV_PREFIXLANG . '_' . $module_data . ' WHERE act=1';
    $list = $nv_Cache->db($sql, 'vid', 'voting');

    if (empty($list) or !isset($list[$vid])) {
        header('location:' . $global_config['site_url']);
        exit();
    }

    $row = $list[$vid];
    if ((int) $row['exp_time'] < 0 or ((int) $row['exp_time'] > 0 and $row['exp_time'] < NV_CURRENTTIME)) {
        header('location:' . $global_config['site_url']);
        exit();
    }

    if (!nv_user_in_groups($row['groups_view'])) {
        header('location:' . $global_config['site_url']);
        exit();
    }

    $difftimeout = 3600;
    $dir = NV_ROOTDIR . '/' . NV_LOGS_DIR . '/voting_logs';
    $log_fileext = preg_match('/[a-z]+/i', NV_LOGS_EXT) ? NV_LOGS_EXT : 'log';
    $pattern = '/^(.*)\.' . $log_fileext . '$/i';
    $logs = nv_scandir($dir, $pattern);

    if (!empty($logs)) {
        foreach ($logs as $file) {
            $vtime = filemtime($dir . '/' . $file);

            if (!$vtime or $vtime <= NV_CURRENTTIME - $difftimeout) {
                @unlink($dir . '/' . $file);
            }
        }
    }

    $array_id = explode(',', $lid);
    $array_id = array_map('intval', $array_id);
    $array_id = array_diff($array_id, array(
        0
    ));
    $count = sizeof($array_id);

    $note = '';

    if ($count) {
        if ($row['active_captcha'] and !nv_capcha_txt($captcha)) {
            die('ERROR|' . $nv_Lang->getGlobal('securitycodeincorrect'));
        }

        $acceptcm = (int) $row['acceptcm'];
        $logfile = md5(NV_LANG_DATA . $global_config['sitekey'] . $client_info['ip'] . $vid) . '.' . $log_fileext;

        if (file_exists($dir . '/' . $logfile)) {
            $timeout = filemtime($dir . '/' . $logfile);
            $timeout = ceil(($difftimeout - NV_CURRENTTIME + $vtime) / 60);
            $note = sprintf($nv_Lang->getModule('timeoutmsg'), $timeout);
        } elseif ($count <= $acceptcm) {
            $in = implode(',', $array_id);
            $sql = 'UPDATE ' . NV_PREFIXLANG . '_' . $module_data . '_rows SET hitstotal = hitstotal+1 WHERE vid =' . $vid . ' AND id IN (' . $in . ')';
            $db->query($sql);
            file_put_contents($dir . '/' . $logfile, '', LOCK_EX);
            $note = $nv_Lang->getModule('okmsg');
        } else {
            $note = ($acceptcm > 1) ? sprintf($nv_Lang->getModule('voting_warning_all'), $acceptcm) : $nv_Lang->getModule('voting_warning_accept1');
        }
    }

    $sql = 'SELECT * FROM ' . NV_PREFIXLANG . '_' . $module_data . '_rows WHERE vid = ' . $vid . ' ORDER BY id ASC';
    $result = $db->query($sql);

    $totalvote = 0;
    $vrow = array();

    while ($row2 = $result->fetch()) {
        $totalvote += (int) $row2['hitstotal'];
        $vrow[] = $row2;
    }

    $pubtime = nv_date('l - d/m/Y H:i', $row['publ_time']);
    $lang = array(
        'total' => $nv_Lang->getModule('voting_total'),
        'counter' => $nv_Lang->getModule('voting_counter'),
        'publtime' => $nv_Lang->getModule('voting_pubtime')
    );
    $voting = array(
        'question' => $row['question'],
        'total' => $totalvote,
        'pubtime' => $pubtime,
        'row' => $vrow,
        'lang' => $lang,
        'note' => $note
    );

    $contents = voting_result($voting);

    include NV_ROOTDIR . '/includes/header.php';
    $is_ajax = $nv_Request->get_int('nv_ajax_voting', 'post');
    if ($is_ajax) {
        echo $contents;
    } else {
        echo nv_site_theme($contents, false);
    }
    include NV_ROOTDIR . '/includes/footer.php';
}