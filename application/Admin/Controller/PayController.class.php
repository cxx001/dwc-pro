<?php
namespace Admin\Controller;

use Common\Controller\AdminbaseController;

class PayController extends AdminbaseController
{

    protected $order_model;
    protected $sign_key='W0PHFHFSFGW0HFBFJKY9F2';

    public function _initialize()
    {
        parent::_initialize();
        $this->order_model = D("Common/Options");
    }

    public function createOrder(){
        $partner_name='listenwindcity';$partner_pwd=strtoupper(md5('listenwindcity'));$re_url='openGetPayPage';
        $order = M('Order');
        $ps = explode('@',$_REQUEST['playerId']);
        $data['player_id']=$_REQUEST['playerId'];
        $data['good_id']=$_REQUEST['goodId'];
        $data['amount']=$_REQUEST['rmb'];
        $data['order_id']=md5(microtime().''.rand().(date_format(date_create(),'YmdHis')).$ps[0]);
        $order->add($data);
        $signData = array();
        $signData['reqUrl'] = $re_url;$signData['fMerchantLoginname']=$partner_name;
        $signData['fMerchantPassword']=$partner_pwd;$signData['totalFee']=$_REQUEST['rmb']/100;$signData['orderId']=$data['order_id'];
        $signData['userId']=$ps[0];$signData['productId']=$_REQUEST['goodId'];
        $sendData = $signData;
        $ntime = time();
        $signData['timestamp']=$ntime;
        $sign = $this->md5Sign($signData,$this->sign_key);
        $sendData['sign']=$sign;
//        $qs = implode('&',$this->sortArrAndToStrArr($sendData));
        $this->ajaxReturn(  'https://www.mengdouwang.cn/md_csp_openapi/pay.html?timestamp='.$ntime.'&'.http_build_query($sendData),'STR');
    }

    public function md5Sign($data,$sign_key=''){
        $sa = $this->sortArrAndToStrArr($data);
        $sr = $sign_key.implode('',$sa);
//        $sr = 'W0PHFHFSFGW0HFBFJKY9F2fBalance6.0fOrderIda42ad7e3cb9349d7a0b97e822f015cb8fTotalFee6.0orderId1fe96923e21ebddfc630717d4c9121d7payType1timestamp1499852558426';
        return strtoupper(md5($sr));
    }

    public function sortArrAndToStrArr($data){
        ksort($data);
        $sa = array();
        foreach($data as $k=>$v){
            if($k!='sign') $sa[]=$k.$v;
        }
        return $sa;
    }

    public function callBackMd(){
        $p = json_decode(file_get_contents('php://input'),true);
//        if(!$p) $p=$_POST;
        send_curl('http://211.149.207.220:3000',$p);
        $getSign = $p['sign'];
        $makeSign = strtoupper($this->md5Sign($p,$this->sign_key));
        if($getSign!=$makeSign){
            $this->ajaxReturn( 'fail','STR');
        }else{
            $order = M('Order');
            $data = $order->where('order_id="'.$p['orderId'].'"')->find();
            $sendData = array();
            $sendData['pre_str']='md';$sendData['order_no']=$p['fOrderId'];$sendData['money']=$p['fTotalFee']*100;
            $sendData['shopId']=$data['good_id'];$sendData['playerId']=$data['player_id'];
            $order->where('order_id="'.$p['orderId'].'"')->save(array('real_money'=>$p['fBalance']));
            $sc = send_curl('http://139.198.3.29:82/payNotify.nd',$sendData);
//            dump($sc);
            $this->ajaxReturn( 'success','STR');
        }
    }

}