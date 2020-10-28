<?php
require_once "./db.php";

ini_set("display_errors", "On");
/*时间*/
function parseTime()
{
    $test = "15分39秒";
    $preg = "~(\d+)分(\d+)秒~";
    preg_match($preg, $test, $matches);
    $time = $matches[1] * 60 + $matches[2];
    var_dump($time);
}

function dataLog($data, $file = "test.json")
{
    $dir = "./logdata/" . date("Y-m-d");
    if (!is_dir($dir)) {
        mkdir($dir);
    }
    file_put_contents($dir . "/" . $file, json_encode($data, JSON_UNESCAPED_UNICODE));
}

exportData();
function exportData()
{
    /*统计各个层,区,职业总人数*/
//    exportNumData();
    /*统计层,区,职业关联人数*/
    exportAreaJobPassData();
}

/**
 * 人数统计
 */
function exportNumData()
{
    $config = getConfig();
    $dbFile = date("Y-m-d") . "dn_rank.db";
    $db     = new Sparrow();
    $db->setDb(["type" => "sqlite3", "database" => $dbFile]);

    $dir = "./rankdata/" . date("Y-m-d") . "/总数";
    if (!is_dir($dir)) {
        mkdir($dir);
    }
    /*层数人数统计*/
    $Jobdata = $db->from("rank_data")
        ->groupBy("pass_num")
        ->sortDesc("pass_num")
        ->select("pass_num,count(*) as num")
        ->many();
    toCsv(
        $Jobdata,
        ["层数", "数量"],
        $dir . "/层数统计.csv"
    );
    /*职业数量统计*/
    $Jobdata = $db->from("rank_data")
        ->groupBy("job_id")
        ->sortDesc("num")
        ->select("dn_job,count(*) as num")
        ->many();
    toCsv(
        $Jobdata,
        ["职业", "数量"],
        $dir . "/职业统计.csv"
    );
    /*区统计*/
    $areaData = $db->from("rank_data")
        ->groupBy("area_id")
        ->sortDesc("num")
        ->select("dn_area,count(*) as num")
        ->many();
    toCsv(
        $areaData,
        ["区服", "数量"],
        $dir . "/区服统计.csv"
    );
}

/**
 * 区 职业 层的统计
 */
function exportAreaJobPassData()
{
    $config = getConfig();
    $dbFile = date("Y-m-d") . "dn_rank.db";
    $db     = new Sparrow();
    $db->setDb(["type" => "sqlite3", "database" => $dbFile]);

    $dir = "./rankdata/" . date("Y-m-d") . "/区职业层数";
    if (!is_dir($dir)) {
        mkdir($dir);
    }

    /*职业 层数*/
    /*职业 区*/
    /*区 层数*/
    /*统计各个层区服分布*/
    $tempAreaPassData = $db->from("rank_data")
        ->groupBy("pass_num,dn_area")
        ->sortDesc("pass_num")
        ->sortDesc("num")
        ->select("pass_num,dn_area,count(*) as num")
        ->many();
    $tempAreaData     = [];
    foreach ($tempAreaPassData as $k => $v) {
        $tempAreaData[$v["dn_area"]][1000]           = $v["dn_area"];
        $tempAreaData[$v["dn_area"]][$v["pass_num"]] = $v["num"];
    }
    foreach ($tempAreaData as $tk => $tv) {
        foreach ($config["pass"] as $pk => $pv) {
            if (!isset($tv[$pv])) {
                $tempAreaData[$tk][$pv] = 0;
            }
        }
        krsort($tempAreaData[$tk]);
    }

    toCsv(
        array_values($tempAreaData),
        array_merge(["区服"], $config["pass"]),
        $dir . "/层数区服统计.csv"
    );

    /*职业层数*/
    $tempJobPassData = $db->from("rank_data")
        ->groupBy("dn_job,pass_num")
        ->sortDesc("pass_num")
        ->sortDesc("num")
        ->select("pass_num,dn_job,count(*) as num")
        ->many();
    $tempJobData     = [];
    foreach ($tempJobPassData as $k => $v) {
        $tempJobData[$v["dn_job"]][1000]           = $v["dn_job"];
        $tempJobData[$v["dn_job"]][$v["pass_num"]] = $v["num"];
    }
    foreach ($tempJobData as $tk => $tv) {
        foreach ($config["pass"] as $pk => $pv) {
            if (!isset($tv[$pv])) {
                $tempJobData[$tk][$pv] = 0;
            }
        }
        krsort($tempJobData[$tk]);
    }
    toCsv(
        array_values($tempJobData),
        array_merge(["职业"], $config["pass"]),
        $dir . "/层数职业统计.csv"
    );
    /*职业区服统计*/
    $tempJobAreaData = $db->from("rank_data")
        ->groupBy("dn_job,dn_area")
        ->sortDesc("num")
        ->select("dn_area,dn_job,count(*) as num")
        ->many();
    $tempJobData     = [];
    foreach ($tempJobAreaData as $k => $v) {
        $tempJobData[$v["dn_job"]][1000]          = $v["dn_job"];
        $tempJobData[$v["dn_job"]][$v["dn_area"]] = $v["num"];
    }
    $tempResultData = [];
    foreach ($config["job"] as $jk => $jv) {
        $tempData    = [];
        $tempData[0] = $jv;
        foreach ($config["area"] as $ak => $av) {
            $tempData[$av] = isset($tempJobData[$jv][$av])
                ? $tempJobData[$jv][$av] : 0;
        }
        $tempResultData[] = $tempData;
    }
    toCsv(
        array_values($tempResultData),
        array_merge(["职业"], $config["area"]),
        $dir . "/职业区服统计.csv"
    );
    exit;

}

/**
 * 工会数据统计
 */
function exportGroupData()
{
    $config = getConfig();
    $dbFile = date("Y-m-d") . "dn_rank.db";
    $db     = new Sparrow();
    $db->setDb(["type" => "sqlite3", "database" => $dbFile]);

    $dir = "./rankdata/" . date("Y-m-d") . "/工会/";
    if (!is_dir($dir)) {
        mkdir($dir);
    }

    /*统计各个工会的职业*/
    $groupData = [];
    foreach ($config["area"] as $k => $v) {
        $groupJobData = [];
        /*各个工会的职业*/
        $tempGroupJobData = $db->from("rank_data")
            ->where("dn_group <> ''")
            ->where("dn_area", $v)
            ->groupBy("dn_group,dn_job")
            ->sortDesc("num")
            ->select("dn_group,dn_job,count(*) as num")
            ->many();
        $tempGroupData    = [];
        foreach ($tempGroupJobData as $tk => $tv) {
            if (!isset($tempGroupData[$tv["dn_group"]])) {
                $tempGroupData[$tv["dn_group"]] = [];
            }
            $tempGroupData[$tv["dn_group"]][$tv["dn_job"]] = $tv["num"];
        }
        foreach ($tempGroupData as $tk => $tv) {
            if (!isset($groupJobData[$tk])) {
                $groupJobData[$tk] = [];
            }
            $tempData = [];
            foreach ($config["job"] as $jk => $jv) {
                $tempData [$jv] = isset($tv[$jv])
                    ? $tv[$jv]
                    : 0;
            }
            $groupJobData[$tk] = array_merge($groupJobData[$tk], array_values($tempData));
        }
        foreach ($groupJobData as $tk => $tv) {
            array_unshift($groupJobData[$tk], $tk);
            array_unshift($groupJobData[$tk], $v);
        }
        $groupData = array_merge($groupData, array_values($groupJobData));
    }

    $areaGroupHead = ["区服", "工会"];
    $areaGroupHead = array_merge($areaGroupHead, $config["job"]);
    toCsv(
        array_values($groupData),
        $areaGroupHead,
        $dir . "/工会职业统计.csv"
    );

    /*统计各个工会的层数*/
    $groupData = [];
    foreach ($config["area"] as $k => $v) {
        $groupJobData      = [];
        $tempGroupPassData = $db->from("rank_data")
            ->where("dn_group <> ''")
            ->where("dn_area", $v)
            ->groupBy("dn_group,pass_num")
            ->sortDesc("pass_num")
            ->select("dn_group,pass_num,count(*) as num")
            ->many();
        $tempPassData      = [];
        foreach ($tempGroupPassData as $tk => $tv) {
            if (!isset($tempPassData[$tv["dn_group"]])) {
                $tempPassData[$tv["dn_group"]] = [];
            }
            $tempPassData[$tv["dn_group"]][$tv["pass_num"]] = $tv["num"];
        }

        foreach ($tempPassData as $tk => $tv) {
            if (!isset($groupJobData[$tk])) {
                $groupJobData[$tk] = [];
            }
            $tempData = [];
            foreach ($config["pass"] as $pk => $pv) {
                $tempData [$pv] = isset($tv[$pv])
                    ? $tv[$pv]
                    : 0;
            }
            $groupJobData[$tk] = array_merge($groupJobData[$tk], array_values($tempData));
        }

        foreach ($groupJobData as $tk => $tv) {
            array_unshift($groupJobData[$tk], $tk);
            array_unshift($groupJobData[$tk], $v);
        }
        $groupData = array_merge($groupData, array_values($groupJobData));
    }

    $areaGroupHead = ["区服", "工会"];
    $areaGroupHead = array_merge($areaGroupHead, $config["pass"]);
    toCsv(
        array_values($groupData),
        $areaGroupHead,
        $dir . "/工会层数统计.csv"
    );


    /*各区工会人数*/
    $areaGroupData = $db->from("rank_data")
        ->groupBy(["area_id", "dn_group"])
        ->sortDesc("num")
        ->select("dn_area,dn_group,count(*) as num")
        ->many();
    $tempAreaGroup = [];
    foreach ($areaGroupData as $k => $v) {
        if (!isset($tempAreaGroup[$v["dn_area"]])) {
            $tempAreaGroup[$v["dn_area"]] = [];
        }
        if ($v["dn_group"]) {
            $tempAreaGroup[$v["dn_area"]][] = [
                $v["dn_area"],
                $v["dn_group"],
                $v["num"]
            ];
        }
    }
    $areaResultData = [];
    foreach ($tempAreaGroup as $tk => $v) {
        $areaResultData = array_merge($areaResultData, $v);
    }
    toCsv(
        $areaResultData,
        ["区分", "工会", "人数"],
        $dir . "/各区工会统计.csv"
    );
}

function toCSV($data, $colHeaders, $file)
{
    $stream = fopen("php://temp/maxmemory", "w+");
    if (!empty($colHeaders)) {
        fputcsv($stream, $colHeaders);
    }
    foreach ($data as $record) {
        fputcsv($stream, $record);
    }

    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);
    file_put_contents($file, $content);
    echo $file . "生成成功" . PHP_EOL;
}

//getDataToDb();
function getDataToDb()
{
    $startTime = date("H:i:s");

    $dir = "./dndata/" . date("Y-m-d");
    if (!is_dir($dir)) { /*读取数据处理*/
        getAreaJobPassData();
    }
    $dataEndTime = date("H:i:s");
    echo "抓取完成于" . $dataEndTime . PHP_EOL;

    $dbFile = date("Y-m-d") . "dn_rank.db";
    $db     = new Sparrow();
    $db->setDb(["type" => "sqlite3", "database" => $dbFile]);
    if (is_file($dbFile)) {
        dbInit();
    }
    $files = glob($dir . "/*.json");
    foreach ($files as $fk => $fv) {
        $data = json_decode(file_get_contents($fv), 1);
        foreach ($data as $k => $v) {
            $insertSql = $db->from('rank_data')
                ->insert($v)
                ->sql();
//            echo $insertSql . PHP_EOL;
            $db->sql($insertSql)->execute();
        }
        echo $fv . "写入完成" . PHP_EOL;
    }
    $endTime = date("H:i:s");

    echo "开始于" . $startTime . "结束于" . $endTime . PHP_EOL;
    /*查看总数*/
    getDbData();
}

//getAreaJobPassData();
/*
 * 区，职业
 * */
function getAreaJobPassData()
{
    $configData = getConfig();
    foreach ($configData["area"] as $ak => $av) {
        foreach ($configData["job"] as $jk => $jv) {
            getAllData($jk, $ak, $jv . "_" . $av);
        }
    }
}

function getAllData(
    $job = 0,
    $area = 0,
    $file = "",
    $pass = 0)
{
    $pageIndex = 1;  /*页数*/
    $pageSize  = 300;
    $tryNum    = 0;

    while (true) {
        $dir = "./dndata/" . date("Y-m-d");
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $fileName = $file
            ? $dir . "/" . $file . "_" . $pageIndex . ".json"
            : $dir . "/" . $pageIndex . ".json";

        $resultData = getRankData($job, $area, $pass, $pageIndex, $fileName);

        if ($resultData > 0) {
            echo $pageIndex . "获取成功,总" . $pageIndex * $pageSize . "数据" . PHP_EOL;
            $pageIndex = $pageIndex + 1;
        } else {
            echo $pageIndex . "获取失败，再次读取" . PHP_EOL;
            $tryNum = $tryNum + 1;
        }

        if ($tryNum >= 3) {
            echo "数据获取完成" . PHP_EOL;
            break;
        }
        $randTime = rand(0, 1);
        sleep($randTime * 2);
    }
    return $pageIndex;
}

function getRankData(
    $job = 0,
    $area = 0,
    $pass = 0,
    $pageIndex = 1,
    $dataFile = "",
    $pagesSize = 300,
    $cacheFile = "page.json"
)
{
    /*请求承诺书*/
    $url   = "http://act2.dn.sdo.com/Project/201909ranking/handler/GetStageRank.ashx?0.9520720126114097";
    $param = [
        "JobCode"   => $job,
        "SeaAreaId" => $area,
        "PassNum"   => $pass,
        "PageIndex" => $pageIndex,
        "Pagesize"  => $pagesSize,

    ];

    $header = [
        "Accept-Encoding: gzip, deflate",
        "Host: act2.dn.sdo.com",
        "Origin: http://act2.dn.sdo.com",
        "Referer: http://act2.dn.sdo.com/Project/201909ranking/",
    ];
    /*抓取网页数据*/
    $result     = httpPost($url, $param, $header);
    $resultData = json_decode($result, 1);
    $html       = isset($resultData["ReturnObject"])
        ? $resultData["ReturnObject"]
        : "";

    /*匹配出数组*/
    $find    = ['~>\s+<~', '~>(\s+\n|\r)~', '~(\s+?)</td>~'];
    $replace = ['><', '>', '</td>'];
    $content = preg_replace($find, $replace, $html);
    $data    = parseResult($content);

    if (count($data) && $job > 0 && $area > 0) { /*按职业的导入数据库*/

        $config = getConfig();
        $jobs   = $config["job"][$job];
        $areas  = $config["area"][$area];
        $preg   = "~(\d+)分(\d+)秒~";
        $user   = [];
        foreach ($data as $k => $v) {
            preg_match($preg, $v[5], $matches);
            $time   = $matches[1] * 60 + $matches[2];
            $user[] = [
                "dn_area"          => $areas,    //区
                "dn_name"          => $v[2],
                "dn_group"         => $v[3],//工会
                "dn_job"           => $jobs,  //职业
                "pass_num"         => $v[4], //通关层数
                "pass_time_string" => $v[5],//通关时间
                "pass_time"        => $time,//通关时间
                "area_id"          => $area, //区id
                "job_id"           => $job //职业id
            ];
        }
        //重置数据
        $data = $user;
    }

    if (count($data) > 0) {
        /*文件缓存*/
        $fileName = $dataFile
            ? $dataFile
            : "./dndata/" . $param["JobCode"] . "_" . $param["SeaAreaId"] . $param["PassNum"] . $param["PageIndex"] . "-data.json";

        var_dump($fileName);

        $cacheData = ["page" => $pageIndex, "size" => $pagesSize];

        file_put_contents($fileName, json_encode($data, JSON_UNESCAPED_UNICODE));
        file_put_contents($cacheFile, json_encode($cacheData, JSON_UNESCAPED_UNICODE));
    }
    return count($data);
}


function parseResult($content)
{
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $content);
    $tr     = $doc->getElementsByTagName('tr');
    $result = [];
    foreach ($tr as $k => $v) {
        $temp = [];
        foreach ($v->childNodes as $ck => $cv) {
            $temp[] = $cv->textContent;
        }
        $result[] = $temp;
    }
    /**
     * ["1","华东电信一区","秋风带着思念","　　、小人物☆","15","2分38秒"]
     * "id",
     * "area",
     * "name",
     * "group",
     * "pass",
     * "time"
     */
    return $result;
}

function httpPost($url, $data, $header, $timeout = 1)
{
    $ch = curl_init();
    if (!empty($header)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_HEADER, 0);//返回response头部信息
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $result = curl_exec($ch);
    return $result;
}


function getConfig()
{
    $config = [
        "job"  => [
            23 => "剑皇",
            24 => "月之领主",
            25 => "狂战士",
            26 => "毁灭者",
            76 => "黑暗复仇者",
            29 => "狙翎",
            30 => "魔羽",
            31 => "影舞者",
            32 => "风行者",
            81 => "银色猎人",
            35 => "火舞",
            36 => "冰灵",
            37 => "时空领主",
            38 => "黑暗女王",
            85 => "黑暗死神",
            41 => "圣骑士",
            42 => "十字军",
            43 => "圣徒",
            44 => "雷神",
            83 => "黑暗教主",
            47 => "重炮手",
            48 => "机械大师",
            50 => "炼金圣士",
            51 => "药剂师",
            87 => "银色机甲师",
            55 => "黑暗萨满",
            56 => "噬魂者",
            58 => "刀锋舞者",
            59 => "灵魂舞者",
            89 => "银色舞灵",
            63 => "烈",
            64 => "影",
            68 => "曜",
            69 => "暗",
            91 => "黑暗修罗",
            73 => "皇家骑士",
            74 => "魔枪骑士",
            93 => "冰魂术士",
            94 => "火灵术士",
            99 => "黑暗破魔师",
            78 => "御灵",
            79 => "破风",
            96 => "碎夜",
            97 => "驭光"
        ],
        "area" => [
            1   => "华东电信一区",
            2   => "华南电信一区",
            4   => "华中电信一区",
            5   => "华东电信二区",
            7   => "全国网通一区",
            18  => "南方电信大区",
            401 => "WEGAME一区一服",
            402 => "WEGAME一区二服",
            41  => "WEGAME二区",
            42  => "WEGAME三区"
        ],
        "pass" => [
            18, 17, 16, 15, 14, 13, 12, 11
        ]
    ];
    return $config;
}

function dataResult()
{
    $user = [
        "dn_area",    //区
        "dn_group",   //工会
        "dn_name",    //昵称
        "dn_job",     //职业
        "pass_num", //通关层数
        "pass_time",//通关时间
        "pass_time_string", //通过时间字符串
        "area_id",  //区id
        "job_id",   //职业id
    ];
    //           职业
    $dn_sql = 'CREATE TABLE rank_data (
      dn_area varchar(150) not null  default "",
      dn_group varchar(150) not null  default "",
      dn_name varchar(150) not null  default "",
      dn_job varchar(150) not null  default "",
      pass_num int not null  default 0,
      pass_time_string varchar(50) not null  default "",
      pass_time int not null  default 600,
      area_id int not null  default 0,
      job_id int not null  default 0
)';

}

//dbInit();

function dbInit()
{
    $dn_sql = 'CREATE TABLE rank_data (
      dn_area varchar(150) not null  default "",
      dn_group varchar(150) not null  default "",
      dn_name varchar(150) not null  default "",
      dn_job varchar(150) not null  default "",
      pass_num int not null  default 0,
      pass_time_string varchar(50) not null  default "",
      pass_time int not null  default 600,
      area_id int not null  default 0,
      job_id int not null  default 0
)';

    $dbFile = date("Y-m-d") . "dn_rank.db";
    $db     = new Sparrow();

    $db->setDb(["type" => "sqlite3", "database" => $dbFile]);

    $db->sql($dn_sql)->execute();
}

//getDbData();
function getDbData()
{
    $dir = "./dndata/" . date("Y-m-d");
    if (!is_dir($dir)) { /*读取数据处理*/
        return [0, 0];
    }
    $num   = 0;
    $files = glob($dir . "/*.json");
    foreach ($files as $fk => $fv) {
        $data = json_decode(file_get_contents($fv), 1);
        $num  = $num + count($data);
    }

    $dbFile = date("Y-m-d") . "dn_rank.db";
    $db     = new Sparrow();
    $db->setDb(["type" => "sqlite3", "database" => $dbFile]);
    $result = $db->from('rank_data')->count();
    var_dump([$num, $result]);
    return [$num, $result];
}






