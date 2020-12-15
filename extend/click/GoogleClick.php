<?php
namespace click;

use app\model\Log;
use Exception;
use Nesk\Puphpeteer\Puppeteer;
use Nesk\Rialto\Data\JsFunction;
use QL\QueryList;
use Nesk\Rialto\Exceptions\Node;

class GoogleClick
{
    private $puppeteer;
    private $browser;
    private $page;
    private $ql;

    public $timeout;
    public $max_page;
    public $sleep_time;
    public $word = '';
    public $screenshot_save_path;
    public $order = 0;
    public $in_nowpage_order = 0;
    public $match;
    private $prevs = 0;
    public $now_page = 1;
    public $shot_every_page = false;
    public $domain;
    public $page_num = 1;
    public $block_image;
    public $stay_time;
    public $proxy;
    public $user_id;
    /**
     * init
     *
     * @param boolean $block_image 是否拦截图片
     * @param integer $timeout 页面超时时间
     * @param integer $max_page 搜索结果最大页数
     * @param integer $sleep_time
     */
    public function __construct($user_id, $block_image = false, $proxy = false, $timeout = 30000, $max_page = 10, $sleep_time=10)
    {
        set_time_limit(0);
        ini_set('memory_limit', '1065M');
        $this->timeout = $timeout;
        $this->max_page = $max_page;
        $this->sleep_time = $sleep_time;
        $this->block_image = $block_image;
        $this->user_id = $user_id;
        // $this->screenshot_save_path = root_path() . 'screenshot' . DIRECTORY_SEPARATOR . date('Ymd') . DIRECTORY_SEPARATOR;

        $this->ql = QueryList::getInstance();
        
        //代理
        if ($proxy && is_bool($proxy)) {
            $this->proxy = $proxy = $this->get_proxy();
        }

        $args = [
            '--no-sandbox',
            '--lang=zh-CN'
        ];
        
        if ($proxy) {
          $args[] = '--proxy-server=' . $proxy;  
        }

        $this->puppeteer = new Puppeteer([
            'ldle_timeout' => 100000
        ]);

        $this->browser = $this->puppeteer->launch([
            'headless' => true,
            'ignoreHTTPSErrors' => true,
            'defaultViewport' => [
                'width' => 1920,
                'height' => 1080,
                'isMobile' => false
            ],
            'args' => $args
        ]);

        $this->page = $this->browser->newPage();
        
        //拦截图片
        if ($this->block_image) {
            $this->page->setRequestInterception(true);

            $this->page->on('request', JsFunction::createWithParameters(['request'])
                ->body('request.resourceType() === "image" || request.resourceType === "media" ? request.abort() : request.continue()'));
        }
        
        try {
            $this->page->tryCatch->setExtraHTTPHeaders([
                'Accept-Language' => 'zh-cn'
            ]);
        } catch (Node\Exception $e) {
            Log::create([
                'user_id' => $user_id,
                'description' => '初始化打开谷歌页面失败',
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'create_time' => time()
            ]);
            throw new Exception("初始化打开谷歌页面失败：{$e->getMessage()}");
        }
        
        $this->page->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.66 Safari/537.36');
        $this->page->goto('https://www.google.com.hk/');
        
    }

    /**
     * 截图保存路径
     */
    public function sceenshot_save_path($path)
    {
        $path = rtrim($path, "\\/");
        if ( ! is_dir($path)) {
            mkdir($path);
            chmod($path, 0755);
        }
        $this->screenshot_save_path = $path . DIRECTORY_SEPARATOR . date('Ymd') . DIRECTORY_SEPARATOR . date('H-i') . DIRECTORY_SEPARATOR;
        return $this;
    }

    /*
     * 模拟用户聚焦输入框并键盘输入
     * @param [type] $word 查询词
     * @return object;
     */
    public function type($word)
    {
        $this->word = $word;
        $keyboard = $this->page->keyboard;
        $this->page->focus('input.gLFyf[name="q"]');
        $keyboard->type($word);
        $keyboard->press('Enter');
        try {
            $this->page->waitForNavigation([
                'timeout' => $this->timeout,
                'waitUntil' => 'load'
            ]);
        } catch (Node\Exception $e) {
            Log::create([
                'user_id' => $this->user_id,
                'description' => '搜索失败',
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'create_time' => time()
            ]);
            throw new Exception("搜索失败：{$e->getMessage()}");
        }
       

        return $this;
    }

    /*
     * 截图
     * @param [type] $filename 保存时文件名，不带尾缀
     * @param boolean $fullPage 是否保存整个页面
     * @return object
     */
    public function screenshot($filename, $fullPage = false)
    {
        if ( ! $this->screenshot_save_path ) {
            $this->sceenshot_save_path(root_path() . 'screenshot' . DIRECTORY_SEPARATOR . str_replace(['?', '*', '$', '&', '/'], '', $this->word));
        }

        if( ! is_dir($this->screenshot_save_path)) {
            mkdir($this->screenshot_save_path, 0755, true);
            chmod($this->screenshot_save_path, 0755);
        }
        try {
            $this->page->tryCatch->screenshot(['path' => $this->screenshot_save_path . $filename . '.png', 'fullPage' => $fullPage]);
        } catch (Node\Exception $e) {
            Log::create([
                'user_id' => $this->user_id,
                'description' => '截图失败',
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'create_time' => time()
            ]);
            throw new Exception("截图失败：{$e->getMessage()}");
        }

        return $this;
    }

    /*
     * 匹配域名
     * @param [type] $match 域名
     * @param boolean $fake
     * @return object
     */
    public function fetch($match, $fake = true)
    {
        $html = $this->page->content();
        
        
        $items = $this->ql
            ->setHtml($html)
            ->range('.g')
            ->rules([
                'site' => ['.rc .yuRUbf>a .TbwUpd>cite', 'text', '-span'],
                'title' => ['.rc .yuRUbf>a h3 span', 'text'],
            ])
            ->queryData();
        
        foreach ($items as $index => $item) {
            if (strpos($item['site'], $match) !== false) {
                $this->match = $match;
                $this->in_nowpage_order = $index;
                // $this->order = $this->prevs + $index + 1; //精准
                $this->order = ($this->now_page - 1) * 10 + $index + 1; //常规
                break;
            }
        }

        if ($fake) {
            $this->fake();
        }

        if ($this->shot_every_page) {
            $date_time = date('ymdHis');
            $this->screenshot('page_' . $this->now_page . '_' . $date_time, true);
        }

        if ( ! $this->order) {
            $this->prevs += count($items);
            
            //是否有下一页 并且未超过最大页数限制
            $new_page_ql = '#pnnext';
            $nex_page_text = $this->ql->setHtml($html)->find($new_page_ql)->text();
            if ($nex_page_text === '下一页' && $this->now_page < $this->max_page) {
                $this->page->click($new_page_ql);
                $this->page->waitForNavigation([
                    'timeout' => $this->timeout,
                    'waitUntil' => 'load'
                ]);
                $this->now_page += 1;
                return $this->fetch($match, $fake);
            } else {
                if ($this->now_page === 10) {
                    $this->order = -1; //前 {$this->max_page} 页中没有匹配到结果
                } else {
                    $this->order = -2; //前 {$this->now_page} 页(结果不足{$this->max_page}页)中没有匹配到结果
                }
            }
        }
       
        return $this;
    }

    /*
     * 假动作，模拟用户
     * @return object
     */
    public function fake()
    {
        $keyboard = $this->page->keyboard;
        $mouse = $this->page->mouse;

        //模拟鼠标移动
        $end_position[] = rand(220, 380); //x
        $end_position[] = rand(220, 580); //y
        //生成路线
        $positions = $this->mouse_positions($end_position);
        
        // $keyboard->press('PageDown');
        foreach ($positions as $position) {
            $mouse->move($position[0], $position[1]);
        }
        
        //滚动条
        $js_str = $this->build_js();
        
        $js_result = $this->page->evaluate(JsFunction::createWithBody($js_str));

        if($this->in_nowpage_order && $js_result && rand(1,2) === 1) {
            $mouse->move($js_result['x'], $js_result['y']);
            $mouse->down();
            $mouse->move($js_result['x'] + $js_result['title_width'], $js_result['y']);
            $mouse->up();
        }


        $this->page->waitFor($js_result['total_await_time']);
        

        return $this;
    }

    /*
     * 生成鼠标移动路径
     * @param [type] $end_position 结束时的位置[x,y]
     * @param integer $x_step x轴步进
     * @param integer $y_step y轴步进
     * @return array
     */
    protected function mouse_positions($end_position, $x_step = 10, $y_step = 8)
    {
        [$x, $y] = $end_position;
        $x_now = 0;
        $y_now = 0;
        $positions = [
            [$x_now, $y_now]
        ];

        $x_midpoint = $x / 2;
        for ($xi = 0; $xi <= $x_midpoint; $xi ++) {
            $x_now += $x_step;
            $y_now += $y_step;
            $positions[] = [$x_now, $y_now];
        }

        $overage_steps = floor($x_midpoint / $x_step);
        $y_step = floor(($y - $y_now) / $overage_steps);

        for ($i = 0; $i <= $overage_steps; $i ++) {
            $x_now += $x_step;
            $y_now += $y_step;
            $positions[] = [$x_now, $y_now];
        }

        return $positions;
    }

    /*
     * 生成模拟用户滚动页面，鼠标移动动作的js
     * @return string
     */
    protected function build_js()
    {
        $str = "
        let _height = document.body.scrollHeight, now_y_positon = 0, _scroll_once = 180, _await_time = 1000, _index = <in_nowpage_order>, _order = <_order>;
            if ( ! _order) {
                let _all_scroll_times = Math.ceil((_height - window.innerHeight) / _scroll_once);
                let _has_scroll_times = 0;
                let _interval = setInterval( () => {
                    now_y_positon += _scroll_once;
                    window.scrollTo({
                        top: now_y_positon,
                        behavior: 'smooth'
                    })
                    _has_scroll_times += 1;
                    if (_has_scroll_times === _all_scroll_times) {
                        clearInterval(_interval);
                    }
                }, _await_time);
                return {total_await_time: (_all_scroll_times + 1) * _await_time};
            } else {
                let _ele = document.getElementsByClassName('g')[_index];
                let x = 178;
                let y = _ele.offsetTop + 210;
                let title_width = _ele.getElementsByTagName('h3')[0].getElementsByTagName('span')[0].offsetWidth,_await_time = 1000;
                
                let _ys = []; 
                
                for (let i = 0; i < _height - window.innerHeight ; i += _scroll_once) {
                    _ys.push(i);
                }

                let _end_y = y - (window.innerHeight / 2);
                if (_end_y < 0){_end_y = 0;}

                for (let i = _height; i >= _end_y; i -= _scroll_once) {
                    if(i < _end_y){i = _end_y};
                    _ys.push(i);
                }
                
                let i = 0;
                let _interval = setInterval(() => {
                    window.scrollTo({
                        top: _ys[i],
                        behavior: 'smooth'
                    })
                    i += 1;
                    if (i >= _ys.length){clearInterval(_interval);}
                }, _await_time);

                return {x, y, title_width, total_await_time: _ys.length * _await_time};
            }
        ";
        return str_replace(['<in_nowpage_order>', '<_order>'], [$this->in_nowpage_order, $this->order > 0 ? $this->order : 0], $str);
    }

    /*
     * 点击到匹配到的网站
     * @return object
     */
    public function attach()
    {
        if ( ! $this->in_nowpage_order) {
           throw new Exception("未找到排名");
        }

        sleep(rand(2,5));
        $box = $this->page->querySelectorAll(".g")[$this->in_nowpage_order];
        $el = $box->querySelector('.yuRUbf a');
        $el->click();
        try {
            $this->page->tryCatch->waitForNavigation([
                'timeout' => $this->timeout,
                'waitUntil' => 'load'
            ]);
        } catch (Node\Exception $e) {
            Log::create([
                'user_id' => $this->user_id,
                'description' => '打开用户网站失败',
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
                'create_time' => time()
            ]);
            throw new Exception("打开用户网站失败：{$e->getMessage()}");
        }
        
        $this->stay_time = time();
        return $this;
    }
    
    /**
     * 模拟用户浏览网站，包括内页点击
     *
     * @param string $host 站点域名 http://xxx.xxx.xxx
     * @param integer $page_num 浏览页面数量（包括从谷歌进入的页面）
     * @param integer $min_time 每个页面最小停留时间
     * @param integer $max_time 每个页面最大停留时间
     * @return object
     */
    public function scan(string $host, int $page_num = 1, int $min_time = 20, int $max_time = 60)
    {
        if ($this->shot_every_page) {
            $this->screenshot(str_replace(['http://', 'https://'], '', $host) . "inner_page_{$this->page_num}", true);
        }
        
        $host = rtrim($host, "/");
        //随机8 ~ 12个a链接，hover
        $points_position = $this->page->evaluate(JsFunction::createWithBody("
            let host = '$host';
            let result = [];
            let links = document.getElementsByTagName('a');
            for (let i in links) {
                if (typeof(links[i].href) == 'undefined') {
                    break;
                }
                if (links[i].href.indexOf('/') === 0) {
                    links[i].href = host + links[i].href;
                }
                if (links[i].href.indexOf(host) !== -1 && links[i].href.indexOf('sitemap') === -1 && links[i].href != host && links[i].href != host + '/' && links[i].offsetTop != 0) {
                    links[i].setAttribute('target', '_self');
                    result.push({
                        width: links[i].clientWidth,
                        height: links[i].clientHeight,
                        x: links[i].offsetLeft,
                        y: links[i].offsetTop,
                        href: links[i].href,
                        text: links[i].innerText,
                        index: i
                    })
                }
            }

            return result;
        "));

        $link_num = rand(8, 12);
        $link_num = $link_num > count($points_position) ? count($points_position) : $link_num;
        //mousehover 点
        $random_keys = array_rand($points_position, $link_num);
        $mouse = $this->page->mouse;
        //mouseclick 点
        $click_key = array_rand($random_keys);
        //每次移动停留时间
        $await_time = ceil(rand($min_time, $max_time) / (count($random_keys) + 1));
        //hover
        foreach ($random_keys as $random_key) {
            $point = $points_position[$random_key];
            $this->visible($point['x'], $point['y'] + $point['height']);
            $mouse->move($point['x'] + $point['width'] / 10, $point['y'] + $point['height'] / 10);
            $is_mouse_down = false;
            if (rand(1, floor($link_num / 2)) === ceil($link_num / 4)) {
                $mouse->down();
                $is_mouse_down = true;
            }
            $mouse->move($point['x'] + $point['width'] * 9 / 10, $point['y'] + $point['height'] * 8 / 10);
            $this->page->waitFor($await_time);
            if ($is_mouse_down) {
                $mouse->up();
            }
        }
        //click
        if ($this->page_num < $page_num) {
            $point = $points_position[$click_key];
            $this->visible($point['x'], $point['y'] + $point['height']);
            $mouse->move($point['x'] + $point['width'] * 9 / 10, $point['y'] + $point['height'] * 8 / 10);
            $this->page->waitFor($await_time);
            $click_ele = $this->page->querySelectorAll('a')[$point['index']];
            $click_ele->click();
            try {
                $this->page->tryCatch->waitForNavigation([
                    'timeout' => $this->timeout,
                    'waitUntil' => 'load'
                ]);
            } catch (Node\Exception $e) {
                Log::create([
                    'user_id' => $this->user_id,
                    'description' => "打开用户网站内页失败 第{$this->page_num}页",
                    'code' => $e->getCode(),
                    'msg' => $e->getMessage(),
                    'create_time' => time()
                ]);
                throw new Exception("打开用户内页网站失败 第{$this->page_num}页：{$e->getMessage()}");
            }
            
            
            $this->page_num += 1;
            return $this->scan($host, $page_num, $min_time, $max_time);
        }
        $this->stay_time = time() - $this->stay_time;
        return $this;
    }

    /**
     * 跳转到指定页面
     *
     * @param string $url
     * @return object
     */
    public function goto(string $url)
    {
        $this->page->goto($url);
        return $this;
    }

    /*
     * 是否每个页面都截图
     * @return object
     */
    public function shotEveryPage($shot = true)
    {
        $this->shot_every_page = $shot;
        return $this;
    }

    /**
     * 获取代理
     *
     * @return string
     */
    public function get_proxy()
    {
        // $api = 'http://tiqu.linksocket.com:81/abroad?num=1&type=2&lb=1&sb=0&flow=1&regions=hk&n=0';
        // $data = $this->ql->get($api)->getHtml();
        // $data = json_decode($data, true);
        // if ($data && $data['code'] === 0) {
        //     return $data['data'][0]['ip'] . ':' . $data['data'][0]['port'];
        // } else {
        //     return false;
        // }

        $api = "https://www.cloudam.cn/ip/takeip/BJp1Kxw4GKSLAyfSQhCQUfDeL1mq6u7r?protocol=proxy&regionid=us&needpwd=false&duplicate=true&amount=1&type=json";
        $data = $this->ql->get($api)->getHtml();
        $data = json_decode($data, true);
        if (isset($data[0])) {
            return $data[0];
        } else {
            return false;
        }
    }

    /**
     * 判断当前坐标是否可见，不可见时翻页，使其可见
     *
     * @param integer $x
     * @param integer $y
     */
    public function visible(int $x, int $y)
    {
        $js_result = $this->page->evaluate(JsFunction::createWithBody("
            let y = {$y}, client_height = window.innerHeight, scrollHeight = window.scrollY, document_height = document.body.clientHeight, detail_y;
            if (y == 0) {
                detail_y = 0;
            } else {
                detail_y = y - Math.ceil(client_height / 2);
            }

            if (detail_y < 0) {
                detail_y = 0;
            }
            if (detail_y > document_height - client_height) {
                detail_y = document_height - client_height;
            }
            //每次滑动半屏
            let scroll_once = client_height;
            let scroll_times = Math.floor(Math.abs(detail_y - scrollHeight) / scroll_once);
            let i = 0;
            let interval = setInterval(() => {
                if (i < scroll_times) {
                    scrollHeight += scroll_once;
                    window.scrollTo({
                        top: scrollHeight,
                        behavior: 'smooth'
                    });
                    i++;
                } else {
                    clearInterval(interval);
                }
            }, 1200)

            window.scrollTo({
                top: detail_y,
                behavior: 'smooth'
            });
            return {waitTime: (scroll_times + 1) * 1200}
        "));
        $this->page->waitFor($js_result['waitTime'] > 25000 ? 25000 : $js_result['waitTime']);
        return $this;
    }



    public function __destruct()
    {
        $this->page->close();
        $this->browser->close();

    }
}