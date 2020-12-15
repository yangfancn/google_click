<?php
declare (strict_types = 1);

namespace app\command;

use app\model\Mask;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Queue;

class Cron extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('app\command\cron')
            ->setDescription('the app\command\cron command');
    }

    protected function execute(Input $input, Output $output)
    {   
        $start_time = time();
        $end_time = strtotime(date('Y-m-d', strtotime('+1 days')));
        $min_step = 300; //每个任务最小间隔
        //发布每天的队列任务
        $masks = Mask::where([['end_time', '>', $start_time]])->select();
        foreach ($masks as $mask) {
            //[$id, $word, $match, $domain, $inner_page_num, $screenshot] = $data;
            $mask->day_has_click = 0;
            $mask->save();
            $data = [$mask->id, $mask->word,  match_host($mask->domain), $mask->domain, $mask->inner_page_num, $mask->screenshot];
            
            $delay_times = [];
            for ($i = 0; $i < $mask->day_click_times - $mask->day_has_click; $i++) {
                $delay_times[$i] = rand($start_time, $end_time);
            }
            sort($delay_times);
            foreach ($delay_times as $k => &$delay_time) {
                if ( $k !== 0 && $delay_time - $delay_times[$k - 1] < $min_step) {
                    $delay_time += $min_step;
                }
                //发布任务
                Queue::later($delay_time - $start_time, 'Click@click', $data, 'clickQueue');
                // Queue::push('Click@click', $data, 'clickQueue');
                
            }
        }
        
        
        // 指令输出
        $output->writeln('任务发布完成');
    }

}
