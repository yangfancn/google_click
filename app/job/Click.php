<?php

namespace app\job;

use app\model\Mask;
use think\queue\Job;
use click\GoogleClick;

class Click{
    public function rank(Job $job, $data)
    {
        [$id, $word, $match] = $data;
        $mask = Mask::find($id);
        $googleClick = new GoogleClick($mask->user_id);
        $googleClick->type($word)->fetch($match)->screenshot("fetch_{$word}_lastPage.png", true);
        
        $maskData = $mask->getData();
        if ($maskData['start_rank'] === 0) {
            $mask->start_rank = $googleClick->order;
        }
        $mask->rank = $googleClick->order;
        $mask->save();
        $job->delete();
    }

    public function click(Job $job, $data)
    {

        if ($job->attempts() > 3) {
            $job->delete();
        }

        [$id, $word, $match, $domain, $inner_page_num, $screenshot] = $data;
        $mask = Mask::find($id);
        $googleClick = new GoogleClick($mask->user_id, false, true);
        $googleClick->shotEveryPage($screenshot === 1 ? true : false)->type($word)->fetch($match);
        if ($googleClick->order) {
            $googleClick->attach()->scan($domain, $inner_page_num);
            $mask->day_has_click += 1;
            $mask->rank = $googleClick->order;
            $mask->update_time = time();
            $mask->save();
        } else {
            
        }
        $job->delete();
    }

    public function failed($data)
    {
        
    }
}