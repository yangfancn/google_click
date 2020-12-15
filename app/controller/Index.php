<?php
namespace app\controller;

use app\BaseController;
use click\GoogleClick;
use app\model\Mask;
use app\model\User;
use app\Request;
use think\facade\Queue;

class Index extends BaseController
{
    public $user_id;
    protected $middleware = [
        'auth' => ['except' => []]
    ];

    public function __construct()
    {
        if (session('?user')) {
            $this->user_id = User::where('name', session('user')['name'])->find()->id;
        }
    }

    public function index()
    {
        return view('manage/index', ['nav' => ['t1' => 'google', 't2' => 'myTask', 'name' => 'Google / 我的任务']]);
    }


    public function get_google_mask()
    {
        $where = [];
        $where[] = ['user_id', '=', $this->user_id];
        $masks = Mask::where($where)->select();
        $count = Mask::count();
        return json(['code' => 0, 'msg' => '', 'count' => $count, 'data' => $masks]);
    }

    public function add_google_mask()
    {
        return view('manage/add_google_mask');
    }

    public function store_google_mask(Request $request)
    {
        $data = $request->post();
        $data['screenshot'] = $data['switch'] === 'on' ? 1 : 0;
        $data['end_time'] = strtotime($data['end_time']);
        
        $validate = new \app\validate\GoogleMask;
        $resutl = $validate->check($data);
        if ( ! $resutl) {
            return json([
                'errorno' => 403,
                'msg' => $validate->getError()
            ]);
        }
        $data['start_rank'] = 0;
        $data['rank'] = 0;
        $data['create_time'] = $data['update_time'] = time();
        $data['user_id'] = $this->user_id;
        $mask = Mask::create($data);
        //发布查询排名任务
        $match = match_host($mask->domain);
        Queue::push('Click@rank', [$mask->id, $mask->word, $match], 'clickQueue');

        return json([
            'errorno' => 0,
            'msg' => 'add success'
        ]);
    }

    public function tt()
    {
        $start_time = time();
        $end_time = strtotime(date('Y-m-d', strtotime('+1 days')));
        $min_step = 300; //每个任务最小间隔
        //发布每天的队列任务
        $masks = Mask::where([['end_time', '>', $start_time]])->select();
        foreach ($masks as $mask) {
            //[$id, $word, $match, $domain, $inner_page_num, $screenshot] = $data;
            $data = [$mask->id, $mask->word,  match_host($mask->domain), $mask->domain, $mask->inner_page_num, $mask->screenshot];
            
            $delay_times = [];
            for ($i = 0; $i < $mask->day_click_times; $i++) {
                $delay_times[$i] = rand($start_time, $end_time);
            }
            sort($delay_times);
            foreach ($delay_times as $k => &$delay_time) {
                if ( $k !== 0 && $delay_time - $delay_times[$k - 1] < $min_step) {
                    $delay_time += $min_step;
                }
                //发布任务
                // Queue::later($delay_time - $start_time, 'Click@rank', $data, 'clickQueue');
                Queue::push('Click@click', $data, 'clickQueue');
                die;
            }
        }
    }

}
