<?php
namespace Admin\Controller;
use Common\Controller\AdminbaseController;
class LogController extends AdminbaseController{
	
	protected $options_model;
	
	public function _initialize() {
		parent::_initialize();
		$this->options_model = D("Common/Options");
	}
	
	public function operate(){
		$type = I('request.type');
		$keyword = I('request.keyword');
		$where['id'] = array('egt', 1);
		if (!empty($type) && $type != 'all'){
			$where['type'] = trim($type);
		}
		if (!empty($keyword)){
			$where['_string'] = sprintf('admin_id = %d or player_id = %d', (int)$keyword, (int)$keyword);
		}
		$all_type = array('all' => '所有记录', 'points' => '上下分记录', 'notice' => '广播操作', 'system' => '系统操作');

		$list = M('Log')->where($where)->order('id DESC')->select();
		$count = M('Log')->where($where)->count();
		$page = $this->page($count, 20);

		$this->assign("page", $page->show('Admin'));

		$this->assign('list', $list);
		$this->assign('type', $all_type);
		$this->assign('chose_type', $type);
		$this->display();
	}
}