# GOOGLE & BAIDU 点击器

## GOOGLE
### 核心文件 /vendor/click/GoogleClick.php
#### 使用方法
```
$googleClick = new GoogleClick(0, false, false);

$googleClcik
    ->shotEveryPage(true) //是否每个页面都截图
    ->type('keywords') //搜索词
    ->fetch('youdomain.com') //匹配网站，如果只查询排名，这一步就可以结束
    ->attact()
    ->scan('http://doamin.com', 3) //点击站内网址，3次
```


## BAIDU


```
coding
```

