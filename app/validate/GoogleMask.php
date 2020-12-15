<?php
declare (strict_types = 1);

namespace app\validate;

use think\Validate;

class GoogleMask extends Validate
{
    /**
     * 定义验证规则
     * 格式：'字段名' =>  ['规则1','规则2'...]
     *
     * @var array
     */
    protected $rule = [
        'word' => 'require|min:3|max:20',
        'domain' => ['require', 'regex' => '/^(http:\/\/|https:\/\/)([a-z0-9]+([a-z0-9-]*(?:[a-z0-9]+))?\.)?[a-z0-9]+([a-z0-9-]*(?:[a-z0-9]+))?(\.us|\.tv|\.org\.cn|\.org|\.net\.cn|\.net|\.mobi|\.me|\.la|\.info|\.hk|\.gov\.cn|\.edu|\.com\.cn|\.com|\.co\.jp|\.co|\.cn|\.cc|\.biz)$/i'],
        'day_click_times' => 'require|number|between:1,200',
        'inner_page_num' => 'require|number|between:1,5',
        'end_time' => 'require|number|length:10',
        'screenshot' => 'number|between:0,1'
    ];

    /**
     * 定义错误信息
     * 格式：'字段名.规则名' =>  '错误信息'
     *
     * @var array
     */
    protected $message = [];
}
