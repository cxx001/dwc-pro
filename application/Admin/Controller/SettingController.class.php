<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class SettingController extends AdminbaseController
{

    protected $options_model;
    public $game10001;
    public $game10002;
    public $game10003;

    public function _initialize()
    {
        parent::_initialize();
        if(sp_get_current_admin_id()==1 && F('admin_host')) $this->_curlHost = F('admin_host');
        $this->options_model = D("Common/Options");
    }

    public function site()
    {
        C(S('sp_dynamic_config'));//加载动态配置
        $option = $this->options_model->where("option_name='site_options'")->find();
        $cmf_settings = $this->options_model->where("option_name='cmf_settings'")->getField("option_value");
        $tpls = sp_scan_dir(C("SP_TMPL_PATH") . "*", GLOB_ONLYDIR);
        $noneed = array(".", "..", ".svn");
        $tpls = array_diff($tpls, $noneed);
        $this->assign("templates", $tpls);

        $adminstyles = sp_scan_dir("public/simpleboot/themes/*", GLOB_ONLYDIR);
        $adminstyles = array_diff($adminstyles, $noneed);
        $this->assign("adminstyles", $adminstyles);
        if ($option) {
            $this->assign(json_decode($option['option_value'], true));
            $this->assign("option_id", $option['option_id']);
        }

        $cdn_settings = sp_get_option('cdn_settings');

        $this->assign("cdn_settings", $cdn_settings);

        $this->assign("cmf_settings", json_decode($cmf_settings, true));

        $this->display();
    }

    public function site_post()
    {
        if (IS_POST) {
            if (isset($_POST['option_id'])) {
                $data['option_id'] = I('post.option_id', 0, 'intval');
            }
            $options = I('post.options/a');

            $configs["SP_SITE_ADMIN_URL_PASSWORD"] = empty($options['site_admin_url_password']) ? "" : md5(md5(C("AUTHCODE") . $options['site_admin_url_password']));
            $configs["SP_DEFAULT_THEME"] = $options['site_tpl'];
            $configs["DEFAULT_THEME"] = $options['site_tpl'];
            $configs["SP_ADMIN_STYLE"] = $options['site_adminstyle'];
            $configs["URL_MODEL"] = $options['urlmode'];
            $configs["URL_HTML_SUFFIX"] = $options['html_suffix'];
            $configs["COMMENT_NEED_CHECK"] = empty($options['comment_need_check']) ? 0 : 1;
            $comment_time_interval = intval($options['comment_time_interval']);
            $configs["COMMENT_TIME_INTERVAL"] = $comment_time_interval;
            $_POST['options']['comment_time_interval'] = $comment_time_interval;
            $configs["MOBILE_TPL_ENABLED"] = empty($options['mobile_tpl_enabled']) ? 0 : 1;
            $configs["HTML_CACHE_ON"] = empty($options['html_cache_on']) ? false : true;

            sp_set_dynamic_config($configs);//sae use same function

            $data['option_name'] = "site_options";
            $data['option_value'] = json_encode($options);
            if ($this->options_model->where("option_name='site_options'")->find()) {
                $result = $this->options_model->where("option_name='site_options'")->save($data);
            } else {
                $result = $this->options_model->add($data);
            }

            $cmf_settings = I('post.cmf_settings/a');
            $banned_usernames = preg_replace("/[^0-9A-Za-z_\x{4e00}-\x{9fa5}-]/u", ",", $cmf_settings['banned_usernames']);
            $cmf_settings['banned_usernames'] = $banned_usernames;

            sp_set_cmf_setting($cmf_settings);

            $cdn_settings = I('post.cdn_settings/a');
            sp_set_option('cdn_settings', $cdn_settings);

            if ($result !== false) {
                $this->success("保存成功！");
            } else {
                $this->error("保存失败！");
            }

        }
    }

    public function password()
    {
        $this->display();
    }

    public function password_post()
    {
        if (IS_POST) {
            if (empty($_POST['old_password'])) {
                $this->error("原始密码不能为空！");
            }
            if (empty($_POST['password'])) {
                $this->error("新密码不能为空！");
            }
            $user_obj = D("Common/Users");
            $uid = sp_get_current_admin_id();
            $admin = $user_obj->where(array("id" => $uid))->find();
            $old_password = I('post.old_password');
            $password = I('post.password');
            if (sp_compare_password($old_password, $admin['user_pass'])) {
                if ($password == I('post.repassword')) {
                    if (sp_compare_password($password, $admin['user_pass'])) {
                        $this->error("新密码不能和原始密码相同！");
                    } else {
                        $data['user_pass'] = sp_password($password);
                        $data['id'] = $uid;
                        $r = $user_obj->save($data);
                        if ($r !== false) {
                            $this->success("修改成功！");
                        } else {
                            $this->error("修改失败！");
                        }
                    }
                } else {
                    $this->error("密码输入不一致！");
                }

            } else {
                $this->error("原始密码不正确！");
            }
        }
    }

    public function redPack()
    {
        $this->display();
    }

    public function dialConfig()
    {
//        $list = F('dialConfig');
//        if (!$list) {
            $list = send_curl($this->_curlHost . '/getDialConfig.nd');
            $list = json_decode($list, true);
//            F('dialConfig', $list);
//        }
        $this->assign('list', $list);
        $this->display();
    }

    public function setdial()
    {
        $checkList = $_POST['od'];
        $nd = $_POST['nd'];
        $sendSwitch = false;
        foreach ($checkList as $k => $v) {
            if($k!='name') $nd[$k] = $nd[$k]+0;
            if ($nd[$k] != $v) {
                $sendSwitch = true;
            }
        }
        F('dialConfig',null);
        if ($sendSwitch) {
            $r = send_curl($this->_curlHost . '/setDialConfig.nd', $nd);
            $r = json_decode($r, true);
//            var_dump($r);
            if ($r['msg'] == 'success') {
                F('dialConfig', $r['data']);
                $this->success('保存成功！',U('Setting/dialConfig'));
            } else {
                $this->error('保存失败！');
            }
        } else {
            $this->error('未修改内容！');
        }
    }

    public function redPack_post()
    {
        $r = send_curl($this->_curlHost . '/sysredpack.nd', array('total' => $_POST['total'] + 0, 'count' => $_POST['count'] + 0));
        if ($r !== false) {
            $this->success('保存成功！');
        } else {
            $this->error('保存失败！');
        }
    }

    public function awardsAllConfig()
    {
//        $as = F('awards');
//        if (!$as) {
            $awards = send_curl($this->_curlHost . '/getawardsconfig.nd');
            $awards = json_decode($awards, true);
//            F('awards', $awards);
            $as = $awards;
//        }
        $as['begin_time'] = date("Y-m-d H:i:s", substr($as['begin_time'], 0, -3));
        $as['end_time'] = date("Y-m-d H:i:s", substr($as['end_time'], 0, -3));
        $this->assign('as', $as);
        $this->display();
    }

    public function awardsConfig()
    {
//        $as = F('awards');
//        if (!$as) {
            $awards = send_curl($this->_curlHost . '/getawardsconfig.nd');
            $awards = json_decode($awards, true);
//            F('awards', $awards);
            $as = $awards;
//        }
        $chose_method = I('request.method');
        if (empty($chose_method)) {
            $chose_method = '1';
        }
        $ad = null;
        foreach ($as['draws'] as $k => $v) {
            if ($v['_id'] == $chose_method) {
                $ad = $v;
                break;
            }
        }
        F('chose_id', $chose_method);
        $list = array(1, 2, 3, 4, 5, 6);
        $html = '';
        foreach ($list as $k => $v) {
            $select = $v == $chose_method ? 'selected' : '';
            $html .= "<option value='" . $v . "' " . $select . ">" . $v . "</option>";
        }
        $this->assign('sc', $html);
        $this->assign('ad', $ad);
        $this->display();
    }

    public function awardsConfig_post()
    {
        //1970-01-01 00:00:00
        if (IS_POST) {
            $begintime = I('post.begin_time');
            $endtime = I('post.end_time');
            $config = $_POST;
            if ($begintime) $config['begin_time'] = strtotime($begintime);
            if ($endtime) $config['end_time'] = strtotime($endtime);
            $id = F('chose_id');
            if ($id) {
                $config['id'] = $id;
            }
            $r = send_curl($this->_curlHost . '/setawardsconfig.nd', $config);
            F('awards', null);
            F('chose_id', null);
            if ($r !== false) {
                $this->success('保存成功！');
            } else {
                $this->error('保存失败！');
            }
        }
    }

    /**
     * 上传限制设置界面
     */
    public function upload()
    {
        $upload_setting = sp_get_upload_setting();
        $this->assign($upload_setting);
        $this->display();
    }

    public function upload_post()
    {
        if (IS_POST) {
            $result = sp_set_option('upload_setting', I('post.'));
            if ($result !== false) {
                $this->success('保存成功！');
            } else {
                $this->error('保存失败！');
            }
        }

    }

    /**
     * 清除缓存
     */
    public function clearcache()
    {
        sp_clear_cache();
        $this->display();
    }


    public function config()
    {
        $gameid = I('get.gameid');
//        dump($this->_curlHost);
        $games = send_curl($this->_curlHost . '/getgame.nd');
        $games = json_decode($games, true);
        $gameid = $gameid ? $gameid : $games[0]['id'];
        foreach ($games as $k => $v) {
            unset($games[$k]);
            $games[$v['id']] = $v;
        }
        $names = '{"game10001":[{"key":"jyx","v":[2,0,0]},{"key":"jyd","v":[2,0,1]}
    ,{"key":"fqx","v":[5,1,0]},{"key":"fqd","v":[5,1,1]}
    ,{"key":"ssr","v":[5,2,0]},{"key":"ser","v":[6,2,0,2]}
    ,{"key":"str","v":[8,2,0]}
    ,{"key":"psr","v":[9,2,1]},{"key":"per","v":[10,2,1,2]}
    ,{"key":"ptr","v":[12,2,1]}
    ],"game10002":[{"key":"pfr","v":["player_percent",0]},{"key":"psr","v":["player_percent",1]},{"key":"ptr","v":["player_percent",2]},{"key":"por","v":["player_percent",3]}
    ,{"key":"pir","v":["player_percent",4]}
    ,{"key":"sfr","v":["sys_percent",0]},{"key":"ssr","v":["sys_percent",1]},{"key":"str","v":["sys_percent",2]},{"key":"sor","v":["sys_percent",3]}
    ,{"key":"sir","v":["sys_percent",4]}
    ,{"key":"BAO_ZI","v":["poke_rate",0]}
    ,{"key":"SHUN_ZI","v":["poke_rate",1]}
    ,{"key":"JIN_HUA","v":["poke_rate",2]}
    ,{"key":"DUI_ZI","v":["poke_rate",3]}
    ,{"key":"DAN_ZH","v":["poke_rate",4]}
    ,{"key":"SHUN_JIN","v":["poke_rate",5]},{"key":"rrr","v":["random_redPack","rate"]},{"key":"rri","v":["random_redPack","min"]},{"key":"rra","v":["random_redPack","max"]}
    ],"game10003":[{"key":"pfr","v":["player_percent",0]},{"key":"psr","v":["player_percent",1]},{"key":"ptr","v":["player_percent",2]},{"key":"por","v":["player_percent",3]}
    ,{"key":"pir","v":["player_percent",4]}
    ,{"key":"sfr","v":["sys_percent",0]},{"key":"ssr","v":["sys_percent",1]},{"key":"str","v":["sys_percent",2]},{"key":"sor","v":["sys_percent",3]}
    ,{"key":"sir","v":["sys_percent",4]}
    ],"game10004":[{"key":"fwg","v":["giftList",0,0]},{"key":"fwn","v":["giftList",0,1]},{"key":"swg","v":["giftList",1,0]},{"key":"swn","v":["giftList",1,1]}
    ,{"key":"twg","v":["giftList",2,0]}
    ,{"key":"twn","v":["giftList",2,1]},{"key":"owg","v":["giftList",3,0]},{"key":"own","v":["giftList",3,1]},{"key":"ffwg","v":["giftList",4,0]}
    ,{"key":"ffwn","v":["giftList",4,1]},{"key":"siwg","v":["giftList",5,0]},{"key":"siwn","v":["giftList",5,1]},{"key":"sewg","v":["giftList",6,0]},{"key":"sewn","v":["giftList",6,1]}
    ,{"key":"ewg","v":["giftList",7,0]},{"key":"ewn","v":["giftList",7,1]},{"key":"nwg","v":["giftList",8,0]},{"key":"nwn","v":["giftList",8,1]}
    ,{"key":"tewg","v":["giftList",9,0]},{"key":"tewn","v":["giftList",9,1]},{"key":"nowg","v":["giftList",10,0]},{"key":"nown","v":["giftList",10,1]}
    ]}';
        $names = json_decode($names);
        $initconfig = array();
        $game = send_curl($this->_curlHost . '/getgame.nd?gameid=' . $gameid);
        $config = send_curl($this->_curlHost . '/getconfig.nd?gameid=' . $gameid);
        $game = json_decode($game, true);
        $config = json_decode($config, true);
        switch ($gameid) {
            case 10001:
                $tpl = 'config001';
                F('game10001', $config);
                $cc = $config['config']['configs'];
                foreach ($names->game10001 as $k => $v) {
                    $v1 = $v->v;
                    $val = $cc[$v1[0]][$v1[1]][$v1[2]];
                    $fv = (isset($val) ? $val : $cc[$v1[0]][$v1[1]][0]) * 100;
                    $initconfig[$v->key] = isset($v1[3]) ? (floor($fv) * $v1[3]) : $fv;
                }
                break;
            case 10002:
                $tpl = 'config002';
                F('game10002', $config);
                foreach ($names->game10002 as $k => $v) {
                    $v1 = $v->v;
                    $cc = $config['config'];
                    $val = $cc[$v1[0]][$v1[1]];
                    $fv = ($v1[0] == 'poke_rate') ? $val['value'] : $val;
                    $initconfig[$v->key] = $fv;
                }
                break;
            case 10003:
                $tpl = 'config003';
                F('game10003', $config);
                foreach ($names->game10003 as $k => $v) {
                    $v1 = $v->v;
                    $cc = $config['config'];
                    $val = $cc[$v1[0]][$v1[1]];
                    $fv = $v1[0] == 'poker_rate' ? $val['value'] : $val;
                    $initconfig[$v->key] = $fv;
                }
                break;
            case 10004:
                $tpl = 'config004';
                F('game10004', $config);
                foreach ($names->game10004 as $k => $v) {
                    $v1 = $v->v;
                    $cc = $config['config'];
                    $val = $cc[$v1[0]][$v1[1]];
                    $fv = $v1[2] == 1 ? $val['note'] : $val['gold'];
                    $initconfig[$v->key] = $fv;
                }
                break;
            case 10006:
                $tpl = 'config006';
                F('game10006', $config);
                $initconfig=$config['config']['configs'];
                break;
            case 20003:
                $tpl = 'config005';
                F('game10005', $config);
                $initconfig=$config['config']['config'];//class="btn btn-primary js-ajax-submit"
                $initconfig['10']=$config['config']['winRand'];
                $initconfig['11']=$config['config']['gifRand'];
                break;
        }
        $initconfig['rate'] = $config['config']['rate'] * 100;
        $this->assign('gameid', $gameid);
        $this->assign('gamename', $games[$gameid]['name']);
        $this->assign('games', $games);
        $this->assign('configs', $initconfig);
        $this->display($tpl);
    }

    /**
     *
     */
    public function config_post()
    {
//        dump($_POST);
//        exit();
        if (IS_POST) {
            $config = null;
            $gameid = I('post.gameid');
            $json = '{"jyx":[[2,3],[5,6,7,8,9,10,11,12],0,0],"jyd":[[2,3],[5,6,7,8,9,10,11,12],0,1],"fqx":[[5,6,7,8],[9,10,11,12],1,0],"fqd":[[5,6,7,8],[9,10,11,12],1,1]
					  ,"ssr":[[5],[],2,0],"ser":[[6,7],[],2,0,2],"str":[[8],[],2,0]
					  ,"psr":[[5],[],2,1],"per":[[6,7],[],2,1,2],"ptr":[[8],[],2,1]
					}';
            $json = json_decode($json, true);
            $switchchange = false;
            switch ($gameid) {
                case 10001: {
                    $game10001 = F('game10001');
                    $initconfig = $game10001['config']['configs'];
                    foreach ($_POST as $k => $v) {
                        if (!in_array($k, array('gameid', 'gamename', 'rate'))) {
                            $ca = $json[$k][0];
                            $ca1 = $json[$k][1];
                            $ca2 = $json[$k][2];
                            $ca3 = $json[$k][3];
                            $dv = 100 - $v;
                            if (isset($json[$k][4])) {
                                $v = ($v + 0) / $json[$k][4];
                            }
                            foreach ($ca as $k1 => $v1) {
                                $cv = $game10001['config']['configs'][$v1][$ca2][$ca3];
                                if ($cv != $v) $switchchange = true;
                                $initconfig[$v1][$ca2][$ca3] = $v / 100;
                            }
                            foreach ($ca1 as $k2 => $v2) {
                                $cvj = $game10001['config']['configs'][$v2][$ca2][$ca3];
                                if ($cvj != $v) $switchchange = true;
                                $initconfig[$v2][$ca2][$ca3] = $dv / 100;
                            }
                        }
                    }
                    $game10001['config']['configs'] = $initconfig;
                    if ($game10001['config']['rate'] != (($_POST['rate'] + 0) / 100)) $switchchange = true;
                    $game10001['config']['rate'] = $_POST['rate'] / 100;
                    $config = $game10001;
                }
                    break;
                case 10002:
                case 10003: {
                    $pfr = I('post.pfr');
                    $psr = I('post.psr');
                    $ptr = I('post.ptr');
                    $por = I('post.por');
                    $pir = I('post.pir');

                    $sfr = I('post.sfr');
                    $ssr = I('post.ssr');
                    $str = I('post.str');
                    $sor = I('post.sor');
                    $sir = I('post.sir');

                    if ($gameid == 10002) {
                        $baozi = I('post.baozi');
                        $shunzi = I('post.shunzi');
                        $jinhua = I('post.jinhua');
                        $duizi = I('post.duizi');
                        $danzhang = I('post.danzhang');
                        $shunjin = I('post.shunjin');
                        $rrr = I('post.rrr');
                        $rri = I('post.rri');
                        $rra = I('post.rra');
                        $rr = array('rate'=>$rrr,'min'=>$rri,'max'=>$rra);
                        $pp = array($pfr, $psr, $ptr, $por, $pir);
                        $sp = array($sfr, $ssr, $str, $sor, $sir);
                        $pr = array($baozi, $shunzi, $jinhua, $duizi, $danzhang, $shunjin);
                        $game10002 = F('game10002');
                        foreach ($game10002['config']['player_percent'] as $k => $v) {
                            $game10002['config']['player_percent'][$k] = $pp[$k] + 0;
                            if (!$switchchange && $v != $pp[$k]) {
                                $switchchange = true;
                            }
                        }
                        foreach ($game10002['config']['sys_percent'] as $k => $v) {
                            $game10002['config']['sys_percent'][$k] = $sp[$k] + 0;
                            if (!$switchchange && $v != $sp[$k]) {
                                $switchchange = true;
                            }
                        }
                        foreach ($game10002['config']['poke_rate'] as $k => $v) {
                            $game10002['config']['poke_rate'][$k]['value'] = $pr[$k] + 0;
                            if (!$switchchange && $v['value'] != $pr[$k]) {
                                $switchchange = true;
                            }
                        }
                        foreach($game10002['config']['random_redPack'] as $k=>$v){
                            $game10002['config']['random_redPack'][$k] =  $rr[$k]+ 0;
                            if (!$switchchange && $v != $rr[$k]) {
                                $switchchange = true;
                            }
                        }
                        if ($game10002['config']['rate'] != (($_POST['rate'] + 0) / 100)) $switchchange = true;
                        $game10002['config']['rate'] = $_POST['rate'] / 100;
                        $config = $game10002;
                        F('game10002', $game10002);
                    } else {
                        $pp = array($pfr, $psr, $ptr, $por, $pir);
                        $sp = array($sfr, $ssr, $str, $sor, $sir);
                        $game10003 = F('game10003');
                        foreach ($game10003['config']['player_percent'] as $k => $v) {
                            $game10003['config']['player_percent'][$k] = $pp[$k] + 0;
                            if (!$switchchange && $v != $pp[$k]) {
                                $switchchange = true;
                            }
                        }
                        foreach ($game10003['config']['sys_percent'] as $k => $v) {
                            $game10003['config']['sys_percent'][$k] = $sp[$k] + 0;
                            if (!$switchchange && $v != $sp[$k]) {
                                $switchchange = true;
                            }
                        }
                        if ($game10003['config']['rate'] != (($_POST['rate'] + 0) / 100)) $switchchange = true;
                        $game10003['config']['rate'] = $_POST['rate'] / 100;
                        $config = $game10003;
                        F('game10003', $game10003);
                    }
                }
                    break;
                case 10004:{
                    $fwg = I('post.fwg');$fwn = I('post.fwn');$swg = I('post.swg');$swn = I('post.swn');
                    $twg = I('post.twg');$twn = I('post.twn');$owg = I('post.owg');$own = I('post.own');
                    $ffwg = I('post.ffwg');$ffwn = I('post.ffwn');$siwg = I('post.siwg');$siwn = I('post.siwn');
                    $sewg = I('post.sewg');$sewn = I('post.sewn');$ewg = I('post.ewg');$ewn = I('post.ewn');
                    $nwg = I('post.nwg');$nwn = I('post.nwn');$tewg = I('post.tewg');$tewn = I('post.tewn');
                    $nowg = I('post.nowg');$nown = I('post.nown');
                    $ps = array('fwg','swg','twg','owg','ffwg','siwg','sewg','ewg','nwg','tewg','nowg');
                    $pp = array();
                    foreach($ps as $k=>$v){
                        $nv = substr_replace($v,'n',-1,1);
                        $na = explode(':',$$v);
                        $nva = explode(':',$$nv);
                        $pp[$k]['gold']=($na[1]?$na[1]:$na[0])+0;
                        $pp[$k]['note']=($nva[1]?$nva[1]:$nva[0])+0;
                    }
                    $game10004 = F('game10004');
                    foreach ($game10004['config']['giftList'] as $k => $v) {
                        $game10004['config']['giftList'][$k]['gold'] = $pp[$k]['gold'];
                        $game10004['config']['giftList'][$k]['note'] = $pp[$k]['note'];
                        if (!$switchchange && ($v['gold'] != $pp[$k]['gold'] || $v['note'] != $pp[$k]['note'])) {
                            $switchchange = true;
                        }
                    }
                    $config = $game10004;
                    F('game10004', $game10004);
                }
                    break;
                case 10006:{
                    $game10006 = F('game10006');
                    foreach ($game10006['config']['configs'] as $k => $v) {
                        $game10006['config']['configs'][$k] = I('post.'.$k)+0;
                        if (!$switchchange && $v!=I('post.'.$k)) {
                            $switchchange = true;
                        }
                    }
                    $config = $game10006;
                    F('game10006', $game10006);
                }break;
                case 20003:{
                    $game10005 = F('game10005');
                    foreach ($game10005['config']['config'] as $k => $v) {
                        $game10005['config']['config'][$k]['per'] = I('post.'.$k)+0;
                        if (!$switchchange && $v['per']!=I('post.'.$k)) {
                            $switchchange = true;
                        }
                    }
                    if(!$switchchange && $game10005['config']['winRand']!=I('post.10')){
                        $switchchange = true;
                    }
                    if(!$switchchange && $game10005['config']['gifRand']!=I('post.11')){
                        $switchchange = true;
                    }
                    $game10005['config']['winRand']=I('post.10')+0;
                    $game10005['config']['gifRand']=I('post.11')+0;
                    $config = $game10005;
                    F('game10005', $game10005);
                }break;
                default:
                    break;
            }
            if ($switchchange) {
//                echo json_encode(array('gameid'=>$gameid,'config'=>$config));
                $r = send_curl($this->_curlHost . '/setconfig.nd', array('gameid' => $gameid, 'config' => $config['config']));
                $this->success('保存成功！');
            } else {
                $this->error('保存失败！');
            }
        }
    }

    public function notice()
    {
        //$rk = I('request.keyword');
        $key = I('request.keyword');
        if (empty($rk)) {
            $key = false;
        }

        $where['content'] = $key ? array('like', '%' . $key . '%') : array('neq', '');
        $list = M('notice')->where($where)->order('id DESC')->select();
        $this->assign('list', $list);
        $count = M('notice')->where($where)->count();
        $page = $this->page($count, 20);

        $this->assign("page", $page->show('Admin'));
        $this->display();
    }

    public function addnotice()
    {
        $this->display();
    }

    public function competitionConfig(){
        $this->display();
    }

    public function competitionConfig_post(){
        if(IS_POST){
            if($_POST['title']=='' || empty($_POST['title'])){
                $this->error('is null');
            }
            $_POST['begin_time'] = strtotime($_POST['begin_time'])*1000;
            $_POST['end_time'] = strtotime($_POST['end_time'])*1000;
            $sc = send_curl($this->_curlHost.'/setcompetition.nd',$_POST);
            $sc = json_decode($sc,true);
            if($sc['msg']=='success'){
                $this->success('保存成功！');
            }else{
                $this->error('保存失败！');
            }
        }
    }

    public function competitionlist(){
        $list = send_curl($this->_curlHost.'/getcompetition.nd');
        $list = json_decode($list,true);
        $answer = array();
        foreach($list as $k=>$v){
            $list[$k]['begin_time'] = date("Y-m-d H:i:s", substr($list[$k]['begin_time'], 0, -3));
            $list[$k]['end_time'] = date("Y-m-d H:i:s", substr($list[$k]['end_time'], 0, -3));
            $list[$k]['ai'] = $k;
            $answer[$k] = $list[$k]['answer'];
        }
        $this->assign('list',$list);
        $this->assign('answer',json_encode($answer,true));
        $this->display();
    }

    public function opencompe(){
        $r = send_curl($this->_curlHost.'/opencompe.nd',$_POST);
        if($r=='success'){
            $this->success('保存成功！');
        }else{
            $this->error('保存失败！');
        }
    }

    public function notice_post()
    {
        if (IS_POST) {
            $data['content'] = I('post.content');
            if (empty($data['content'])) {
                $this->error('数据传输失败');
            }
            $data['create_time'] = date("Y-m-d H:i:s", time());
            $result = M('notice')->add($data);
            if ($result !== false) {
                $log['type'] = 'notice';
                $log['action'] = 'add';
                $log['fields'] = 'content';
                $log['old_data'] = '';
                $log['new_data'] = $data['content'];
                $this->save_log($log);
                $this->success('保存成功！');
            } else {
                $this->error('保存失败！');
            }
        }
    }

    public function ban()
    {
        $id = I('get.id', 0, 'intval');
        if ($id) {
            $result = M("Notice")->where(array("id" => $id))->find();
            if ($result) {
                $r = send_curl($this->_curlHost.'/broadcast.nd',array('msg'=>$result['content']));
                if($r=='success'){
                    $this->success("发送成功！");
                }else{
                    $this->error('发送失败！');
                }
            } else {
                $this->error('发送失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    public function cancelban()
    {
        $id = I('get.id', 0, 'intval');
        if ($id) {
            $result = M("Notice")->where(array("id" => $id))->setField('status', 1);
            if ($result) {
                $log['type'] = 'notice';
                $log['action'] = 'update';
                $log['fields'] = 'status';
                $log['old_data'] = 0;
                $log['new_data'] = 1;
                $this->save_log($log);
                $this->success("启用成功！", U("setting/notice"));
            } else {
                $this->error('启用失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    public function buglist()
    {
        $list = send_curl($this->_curlHost . '/getbuglist.nd');
        $list = json_decode($list, true);
        $page = $this->page($list['count'], 20);
        $data = array();
        if (is_array($list['list'])) {
            foreach ($list['list'] as $k => $v) {
                foreach ($v as $k1 => $v1) {
                    if ($k1 == 'time') {
                        $data[$k][$k1] = date("Y-m-d H:i:s", substr($v1, 0, -3));
                    } else $data[$k][$k1] = $v1;
                }
                $data[$k]['state'] = $data[$k]['state']>0?'已采纳':'未采纳';
            }
            $this->assign('sd', $data);
            $this->assign("page", $page->show('Admin'));
            $this->display();
        }
    }

    public function getad()
    {
        if (isset($_POST['id'])) {
            $list = send_curl($this->_curlHost . '/getbug.nd?id=' . $_POST['id']);
            if ($list=='success') {
                $this->success('采用成功！');
            } else {
                $this->error('采用失败！');
            }
        }
    }

    public function giftlist(){
        $list = send_curl($this->_curlHost.'/getgiftslist.nd');
        $list = json_decode($list,true);
        foreach($list as $k=>$v){
            $type = $list[$k]['type'];
            $tn = null;
            switch($type){
                case 1:{
                    $tn='金币';
                }break;
                case 2:{
                    $tn='点券';
                }break;
                default:{
                    $tn = '点券';
                }break;
            }
            $list[$k]['typename'] = $tn;
        }
        $this->assign('list',$list);
        $this->assign('listget',json_encode($list,true));
        $this->display();
    }

    public function setgiftconfig(){
        if(IS_POST){
            $r = send_curl($this->_curlHost.'/setgiftconfig.nd',$_POST);
            if($r=='success'){
                $this->success('修改完成！');
            }else{
                $this->error('修改失败！');
            }
        }
    }

    public function getCompetitionResultList(){
        $list = send_curl($this->_curlHost.'getcrl.nd');
    }

    public function changeIP(){
        $this->display();
    }

    public function exchangeRecord(){
        $data = send_curl($this->_curlHost.'/getExchangeRecord.nd');
        $data = json_decode($data,true);
        $list = $data['list'];
        $count = $data['count'];
//        var_dump($list);
        foreach($list as $k=>$v){
//            dump($v);
            $list[$k]['exchange_time']=date("Y-m-d H:i:s", substr($v['exchange_time'], 0, -3));
            $list[$k]['status_name']=$v['status']==1?'未发货':'已发货';
        }
//        var_dump($list);
        $this->assign('list',$list);
        $this->display();
    }

    public function sendGift(){
        $id = I('post.id');
        $r = send_curl($this->_curlHost.'/sendGift.nd?exchangeId='.$id);
        if($r){
            $this->success('状态已修改！');
        }else{
            $this->error('状态修改失败！');
        }
    }
}