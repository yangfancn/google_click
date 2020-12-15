<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * @mixin \think\Model
 */
class Mask extends Model
{
    //

    public function getEndTimeAttr($v, $data) {
        return date('Y-m-d H:i', $v);
    }

    public function getStartRankAttr($v, $data) {
        switch ($v) {
            case -2:
                return '前10页没有排名（不足10页）';
                break;
            case -1: 
                return '前10页没有排名';
                break;
            case 0:
                return '<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>';
                break;
            default:
                return $v;
        }
    }

    public function getRankAttr($v, $data) {
        switch ($v) {
            case -2:
                return '前10页没有排名（不足10页）';
                break;
            case -1: 
                return '前10页没有排名';
                break;
            case 0:
                return '<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i>';
                break;
            default:
                return $v;
        }
    }
}
