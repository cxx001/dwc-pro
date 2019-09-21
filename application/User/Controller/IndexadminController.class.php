<?php
namespace User\Controller;

use Common\Controller\AdminbaseController;

class IndexadminController extends AdminbaseController {
	public function _initialize()
	{
		parent::_initialize();
		if(sp_get_current_admin_id()==1 && F('admin_host')) $this->_curlHost = F('admin_host');
		$this->options_model = D("Common/Options");
	}
    public function index(){
		$url = $this->_curlHost . '/select.nd';
		$pre = F('admin_pre');
		$chose_method = I('request.method');
		if(empty($chose_method)){
			$chose_method = 'all';
		}
		$url .= '?pre='.$pre.'&method=' . $chose_method;
		$rk = I('request.keyword');
		if (!empty($rk)){
			$url .= '&account=' . I('request.keyword');
		}else{
			$p = I('request.p') ? I('request.p') : 1;
			$url .= '&p=' . $p;
		}
		$list = send_curl($url);
		$list = json_decode($list, true);
		if ($list){
			$page = $this->page($list['count'], 20);
			foreach ($list['list'] as $k => $v){
				$list['list'][$k]['lastLoginTime'] = date("Y-m-d H:i:s", substr($v['lastLoginTime'], 0, -3));
				$list['list'][$k]['registerTime'] = date("Y-m-d H:i:s", substr($v['registerTime'], 0, -3));
				$list['list'][$k]['totalMoney'] = $v['totalMoney']/100;
			}
			$this->assign('list', $list['list']);
			$this->assign('pm',isset($list['money'])?$list['money']/100:null);
			$this->assign("page", $page->show('Admin'));
		}
		$method = array('all' => '所有玩家','online' => '在线玩家', 'add' => '当日新增', 'recharge' => '充值玩家', 'again' => '次日留存');
		$this->assign("method", $method);
		$this->assign('admin_id',sp_get_current_admin_id());
		$this->assign("chose_method", $chose_method);

    	$this->display(":index");
    }
    
    public function ban(){
    	$id= I('get.id',0,'intval');
    	if ($id) {
    		$result = M("Users")->where(array("id"=>$id,"user_type"=>2))->setField('user_status',0);
    		if ($result) {
    			$this->success("会员拉黑成功！", U("indexadmin/index"));
    		} else {
    			$this->error('会员拉黑失败,会员不存在,或者是管理员！');
    		}
    	} else {
    		$this->error('数据传入失败！');
    	}
    }
    
    public function cancelban(){
    	$id= I('get.id',0,'intval');
    	if ($id) {
    		$result = M("Users")->where(array("id"=>$id,"user_type"=>2))->setField('user_status',1);
    		if ($result) {
    			$this->success("会员启用成功！", U("indexadmin/index"));
    		} else {
    			$this->error('会员启用失败！');
    		}
    	} else {
    		$this->error('数据传入失败！');
    	}
    }

	public function points(){
		$status = I('post.status');
		$account = I('post.account');
		$points = I('post.points');
		if (empty($status) || empty($account) || empty($points)){
			exit("数据错误");
		}
		switch ($status){
			case '1':
				$points = $points;
				break;
			case '-1':
				$points = $points * -1;
				break;
			default:
				exit("类型错误");
		}
		$url = $this->_curlHost . '/addgold.nd?account='.$account . '&gold='. $points;
		$rs = send_curl($url);
		$action = $points > 0 ? 'up_points' : 'down_points';
		if ($rs == 'success'){
			$log['type'] = 'points';
			$log['action'] = $action;
			$log['fields'] = 'gold';
			$log['old_data'] = 0;
			$log['new_data'] = $points;
			$this->save_log($log);
		}
		echo $rs;
	}

	public function setFreeze(){
		$account = I('post.account');
		$rs = send_curl($this->_curlHost.'/setFreeze.nd?playerId='.$account);
		if($rs=='success'){
			$this->success('保存成功！');
		}else{
			$this->error('保存失败！');
		}
	}
	public function jcFreeze(){
		$account = I('post.account');
		$rs = send_curl($this->_curlHost.'/jcFreeze.nd?playerId='.$account);
		if($rs=='success'){
			$this->success('保存成功！');
		}else{
			$this->error('保存失败！');
		}
	}
}
