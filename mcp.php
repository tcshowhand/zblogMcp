<?php
/**
 * zblogMcp - MCP Server 端点
 * 实现 JSON-RPC 2.0 协议，提供 MCP Tools 供外部 AI 客户端调用
 *
 * 访问地址: /zb_users/plugin/zblogMcp/mcp.php
 * 认证方式: Authorization: Bearer <api_key>
 */

$dir = dirname(dirname(dirname(__DIR__)));
require $dir . '/zb_system/function/c_system_base.php';
require $dir . '/zb_system/function/c_system_admin.php';

$zbp->Load();

# 加载插件 include
require_once __DIR__ . '/include.php';

# ========== MCP Server 核心 ==========

header('Content-Type: application/json; charset=utf-8');

# 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    zblogmcp_error_response(null, -32600, 'Only POST method is accepted');
}

# 解析 JSON-RPC 请求体
$raw_body = file_get_contents('php://input');
$request = json_decode($raw_body, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($request)) {
    zblogmcp_error_response(null, -32700, 'Parse error: invalid JSON');
}

$method = isset($request['method']) ? $request['method'] : '';
$params = isset($request['params']) ? $request['params'] : [];
$request_id = isset($request['id']) ? $request['id'] : null;

# ========== 认证（initialize / tools/list 不需要认证，便于 AI 无授权发现能力）==========
$api_key_record = null;
if ($method !== 'initialize' && $method !== 'notifications/initialized' && $method !== 'tools/list') {
    $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    $bearer_token = '';
    if (preg_match('/^Bearer\s+(.+)$/i', $auth_header, $m)) {
        $bearer_token = trim($m[1]);
    }
    if (empty($bearer_token)) {
        zblogmcp_error_response($request_id, -32001, 'Unauthorized: missing or invalid API key');
    }
    $api_key_record = zblogmcp_validate_key($bearer_token);
    if (!$api_key_record) {
        zblogmcp_error_response($request_id, -32001, 'Unauthorized: API key is invalid or disabled');
    }
}
$GLOBALS['zblogmcp_current_key'] = $api_key_record;

# ========== 记录请求开始时间 ==========
$start_time = microtime(true);

# ========== 路由分发 ==========
try {
    switch ($method) {
        case 'initialize':
            $result = zblogmcp_handle_initialize($params);
            break;
        case 'notifications/initialized':
            # 通知类型，无需返回
            zblogmcp_log_request($request, null, $api_key_record, $start_time, 1);
            exit;
        case 'tools/list':
            $result = zblogmcp_handle_tools_list();
            break;
        case 'tools/call':
            $result = zblogmcp_handle_tools_call($params);
            break;
        case 'resources/list':
            $result = zblogmcp_handle_resources_list();
            break;
        case 'resources/read':
            $result = zblogmcp_handle_resources_read($params);
            break;
        default:
            zblogmcp_error_response($request_id, -32601, "Method not found: {$method}");
    }

    # 记录成功日志
    zblogmcp_log_request($request, $result, $api_key_record, $start_time, 1);
    zblogmcp_success_response($request_id, $result);

} catch (Exception $e) {
    # 记录失败日志
    zblogmcp_log_request($request, ['error' => $e->getMessage()], $api_key_record, $start_time, 0);
    zblogmcp_error_response($request_id, -32000, $e->getMessage());
}

# ========== MCP 协议处理函数 ==========

/**
 * initialize - 返回服务器能力信息
 */
function zblogmcp_handle_initialize($params)
{
    return [
        'protocolVersion' => '2024-11-05',
        'capabilities'    => [
            'tools'     => ['listChanged' => false],
            'resources' => ['subscribe' => false, 'listChanged' => false],
        ],
        'serverInfo' => [
            'name'    => 'Z-Blog MCP Server',
            'version' => '1.0.0',
        ],
    ];
}

/**
 * tools/list - 返回可用工具列表
 */
function zblogmcp_handle_tools_list()
{
    $tools = [
        [
            'name'        => 'zblog_list_articles',
            'description' => '获取文章列表，支持按分类、作者、关键词筛选，支持分页',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'count'    => ['type' => 'integer', 'description' => '返回数量，默认10，最大50'],
                    'cate_id'  => ['type' => 'integer', 'description' => '分类ID，0表示全部分类'],
                    'search'   => ['type' => 'string', 'description' => '搜索关键词'],
                    'status'   => ['type' => 'integer', 'description' => '文章状态：0=公开, 1=草稿, 2=审核'],
                    'page'     => ['type' => 'integer', 'description' => '页码，从1开始'],
                ],
            ],
        ],
        [
            'name'        => 'zblog_get_article',
            'description' => '根据文章ID获取单篇文章的完整内容',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => '文章ID'],
                ],
                'required' => ['id'],
            ],
        ],
        [
            'name'        => 'zblog_write_article',
            'description' => '创建新文章或更新已有文章。新建时不需要id，更新时需要提供id',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'id'       => ['type' => 'integer', 'description' => '文章ID（更新时必填，新建时不填）'],
                    'title'    => ['type' => 'string', 'description' => '文章标题'],
                    'content'  => ['type' => 'string', 'description' => '文章正文内容（支持HTML）'],
                    'intro'    => ['type' => 'string', 'description' => '文章摘要，不填则自动截取前200字'],
                    'cate_id'  => ['type' => 'integer', 'description' => '分类ID'],
                    'tag'      => ['type' => 'string', 'description' => '标签，多个标签用逗号分隔'],
                    'meta'     => ['type' => 'object', 'description' => '扩展元数据，兼容主题/插件的 Meta 字段，可直接传对象或 JSON 字符串'],
                    'status'   => ['type' => 'integer', 'description' => '状态：0=公开发布, 1=草稿, 2=审核中。不传时使用插件设置中的文章默认写入状态'],
                    'alias'    => ['type' => 'string', 'description' => '文章别名（用于URL）'],
                    'author_id'=> ['type' => 'integer', 'description' => '作者ID，默认为1'],
                ],
                'required' => ['title', 'content'],
            ],
        ],
        [
            'name'        => 'zblog_list_categories',
            'description' => '获取所有文章分类列表',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => (object)[],
            ],
        ],
        [
            'name'        => 'zblog_create_category',
            'description' => '创建新的文章分类',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'name'      => ['type' => 'string', 'description' => '分类名称'],
                    'alias'     => ['type' => 'string', 'description' => '分类别名（用于URL，仅英文数字和横线）'],
                    'parent_id' => ['type' => 'integer', 'description' => '父分类ID，0或不填表示顶级分类'],
                    'template'  => ['type' => 'string', 'description' => '分类模板，留空使用默认模板'],
                ],
                'required' => ['name'],
            ],
        ],
        [
            'name'        => 'zblog_list_tags',
            'description' => '查询博客标签及文章使用数量',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'count' => ['type' => 'integer', 'description' => '返回数量，默认20，最大50'],
                    'page' => ['type' => 'integer', 'description' => '页码，从1开始'],
                    'search' => ['type' => 'string', 'description' => '按标签名称搜索'],
                    'type' => ['type' => 'integer', 'description' => '标签类型，默认0'],
                ],
            ],
        ],
        [
            'name' => 'zblog_get_tag',
            'description' => '读取单个标签详情',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => '标签ID'],
                    'name' => ['type' => 'string', 'description' => '标签名称，未提供ID时使用'],
                ],
            ],
        ],
        [
            'name' => 'zblog_save_tag',
            'description' => '创建或更新博客标签',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => '标签ID，更新时提供'],
                    'name' => ['type' => 'string', 'description' => '标签名称'],
                    'alias' => ['type' => 'string', 'description' => '标签别名'],
                    'intro' => ['type' => 'string', 'description' => '标签简介'],
                    'type' => ['type' => 'integer', 'description' => '标签类型，默认0'],
                ],
                'required' => ['name'],
            ],
        ],
        [
            'name' => 'zblog_delete_tag',
            'description' => '删除博客标签',
            'inputSchema' => [
                'type' => 'object',
                'properties' => ['id' => ['type' => 'integer', 'description' => '标签ID']],
                'required' => ['id'],
            ],
        ],
        [
            'name' => 'zblog_list_media',
            'description' => '查询博客媒体文件和附件',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'count' => ['type' => 'integer', 'description' => '返回数量，默认20，最大50'],
                    'page' => ['type' => 'integer', 'description' => '页码，从1开始'],
                    'author_id' => ['type' => 'integer', 'description' => '按上传者筛选'],
                    'post_id' => ['type' => 'integer', 'description' => '按关联文章筛选'],
                ],
            ],
        ],
        [
            'name' => 'zblog_get_media',
            'description' => '读取单个媒体文件详情和访问地址',
            'inputSchema' => [
                'type' => 'object',
                'properties' => ['id' => ['type' => 'integer', 'description' => '媒体ID']],
                'required' => ['id'],
            ],
        ],
        [
            'name' => 'zblog_upload_media',
            'description' => '通过Base64上传图片或附件',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => '文件名，必须包含合法扩展名'],
                    'data' => ['type' => 'string', 'description' => 'Base64数据，也支持 data:...;base64, 前缀'],
                    'mime_type' => ['type' => 'string', 'description' => 'MIME类型'],
                    'source_name' => ['type' => 'string', 'description' => '原始文件名'],
                    'author_id' => ['type' => 'integer', 'description' => '上传者ID，默认1'],
                    'post_id' => ['type' => 'integer', 'description' => '关联文章ID'],
                    'intro' => ['type' => 'string', 'description' => '媒体说明'],
                ],
                'required' => ['name', 'data'],
            ],
        ],
        [
            'name' => 'zblog_delete_media',
            'description' => '删除媒体记录及对应文件',
            'inputSchema' => [
                'type' => 'object',
                'properties' => ['id' => ['type' => 'integer', 'description' => '媒体ID']],
                'required' => ['id'],
            ],
        ],
        [
            'name' => 'zblog_list_comments',
            'description' => '查询文章评论和审核状态',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'count' => ['type' => 'integer', 'description' => '返回数量，默认20，最大50'],
                    'page' => ['type' => 'integer', 'description' => '页码，从1开始'],
                    'post_id' => ['type' => 'integer', 'description' => '按文章ID筛选'],
                    'is_checking' => ['type' => 'integer', 'description' => '按审核状态筛选：0正常，1待审核'],
                    'root_id' => ['type' => 'integer', 'description' => '按根评论ID筛选'],
                    'parent_id' => ['type' => 'integer', 'description' => '按父评论ID筛选'],
                ],
            ],
        ],
        [
            'name' => 'zblog_get_comment',
            'description' => '读取单条评论详情',
            'inputSchema' => [
                'type' => 'object',
                'properties' => ['id' => ['type' => 'integer', 'description' => '评论ID']],
                'required' => ['id'],
            ],
        ],
        [
            'name' => 'zblog_create_comment',
            'description' => '为文章创建评论或回复，默认进入审核状态',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'post_id' => ['type' => 'integer', 'description' => '文章ID'],
                    'content' => ['type' => 'string', 'description' => '评论内容'],
                    'name' => ['type' => 'string', 'description' => '评论者名称，默认MCP'],
                    'email' => ['type' => 'string', 'description' => '评论者邮箱'],
                    'homepage' => ['type' => 'string', 'description' => '评论者主页'],
                    'parent_id' => ['type' => 'integer', 'description' => '父评论ID，用于创建回复'],
                    'is_checking' => ['type' => 'integer', 'description' => '是否待审核，默认1'],
                ],
                'required' => ['post_id', 'content'],
            ],
        ],
        [
            'name' => 'zblog_review_comment',
            'description' => '切换评论的审核状态',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => '评论ID'],
                    'is_checking' => ['type' => 'integer', 'description' => '1待审核，0通过审核'],
                ],
                'required' => ['id', 'is_checking'],
            ],
        ],
        [
            'name' => 'zblog_delete_comment',
            'description' => '删除评论及其子评论',
            'inputSchema' => [
                'type' => 'object',
                'properties' => ['id' => ['type' => 'integer', 'description' => '评论ID']],
                'required' => ['id'],
            ],
        ],
    ];

    $article_meta_schema = zblogmcp_get_article_meta_schema_text();
    if ($article_meta_schema !== '') {
        foreach ($tools as &$tool) {
            if ($tool['name'] === 'zblog_get_article' || $tool['name'] === 'zblog_write_article') {
                $tool['description'] .= "\n\n文章扩展字段说明：\n" . $article_meta_schema;
            }
            if ($tool['name'] === 'zblog_write_article' && isset($tool['inputSchema']['properties']['meta']['description'])) {
                $tool['inputSchema']['properties']['meta']['description'] .= "\n" . $article_meta_schema;
            }
        }
        unset($tool);
    }

    $key_record = isset($GLOBALS['zblogmcp_current_key']) ? $GLOBALS['zblogmcp_current_key'] : null;
    if ($key_record) {
        $tools = array_values(array_filter($tools, function ($tool) {
            $key_record = isset($GLOBALS['zblogmcp_current_key']) ? $GLOBALS['zblogmcp_current_key'] : null;
            return zblogmcp_key_can_use_tool($key_record, $tool['name']);
        }));
    }
    return ['tools' => $tools];
}

/**
 * tools/call - 调用具体工具
 */
function zblogmcp_handle_tools_call($params)
{
    $tool_name = isset($params['name']) ? $params['name'] : '';
    $arguments = isset($params['arguments']) ? $params['arguments'] : [];
    zblogmcp_require_tool_scope($tool_name);

    switch ($tool_name) {
        case 'zblog_list_articles':
            return zblogmcp_tool_list_articles($arguments);
        case 'zblog_get_article':
            return zblogmcp_tool_get_article($arguments);
        case 'zblog_write_article':
            return zblogmcp_tool_write_article($arguments);
        case 'zblog_list_categories':
            return zblogmcp_tool_list_categories($arguments);
        case 'zblog_create_category':
            return zblogmcp_tool_create_category($arguments);
        case 'zblog_list_tags':
            return zblogmcp_tool_list_tags($arguments);
        case 'zblog_get_tag':
            return zblogmcp_tool_get_tag($arguments);
        case 'zblog_save_tag':
            return zblogmcp_tool_save_tag($arguments);
        case 'zblog_delete_tag':
            return zblogmcp_tool_delete_tag($arguments);
        case 'zblog_list_media':
            return zblogmcp_tool_list_media($arguments);
        case 'zblog_get_media':
            return zblogmcp_tool_get_media($arguments);
        case 'zblog_upload_media':
            return zblogmcp_tool_upload_media($arguments);
        case 'zblog_delete_media':
            return zblogmcp_tool_delete_media($arguments);
        case 'zblog_list_comments':
            return zblogmcp_tool_list_comments($arguments);
        case 'zblog_get_comment':
            return zblogmcp_tool_get_comment($arguments);
        case 'zblog_create_comment':
            return zblogmcp_tool_create_comment($arguments);
        case 'zblog_review_comment':
            return zblogmcp_tool_review_comment($arguments);
        case 'zblog_delete_comment':
            return zblogmcp_tool_delete_comment($arguments);
        default:
            throw new Exception("Unknown tool: {$tool_name}");
    }
}

function zblogmcp_require_tool_scope($tool_name)
{
    $key_record = isset($GLOBALS['zblogmcp_current_key']) ? $GLOBALS['zblogmcp_current_key'] : null;
    if (!zblogmcp_key_can_use_tool($key_record, $tool_name)) {
        throw new Exception("Permission denied for tool: {$tool_name}");
    }
}

/**
 * resources/list - 返回资源列表
 */
function zblogmcp_handle_resources_list()
{
    return ['resources' => []];
}

/**
 * resources/read - 读取资源
 */
function zblogmcp_handle_resources_read($params)
{
    throw new Exception('Resource not found');
}

# ========== MCP Tool 实现 ==========

/**
 * 工具：获取文章列表
 */
function zblogmcp_tool_list_articles($args)
{
    global $zbp;

    $count = isset($args['count']) ? min((int) $args['count'], 50) : 10;
    $cate_id = isset($args['cate_id']) ? (int) $args['cate_id'] : 0;
    $search = isset($args['search']) ? trim($args['search']) : '';
    $status = isset($args['status']) ? (int) $args['status'] : 0;
    $page = isset($args['page']) ? max((int) $args['page'], 1) : 1;

    $w = [];
    $w[] = ['=', 'log_Type', ZC_POST_TYPE_ARTICLE];

    if ($cate_id > 0) {
        $w[] = ['=', 'log_CateID', $cate_id];
    }
    if (!empty($search)) {
        $w[] = ['search', 'log_Title', $search];
    }
    $w[] = ['=', 'log_Status', $status];

    $order = ['log_ID' => 'DESC'];
    $offset = ($page - 1) * $count;

    $articles = $zbp->GetPostList(
        ['*'],
        $w,
        $order,
        [$offset, $count],
        null
    );

    $list = [];
    foreach ($articles as $article) {
        $list[] = [
            'id'          => $article->ID,
            'title'       => $article->Title,
            'intro'       => $article->Intro,
            'cate_id'     => $article->CateID,
            'cate_name'   => $article->Category ? $article->Category->Name : '',
            'author_id'   => $article->AuthorID,
            'author_name' => $article->Author ? $article->Author->Name : '',
            'status'      => $article->Status,
            'post_time'   => date('Y-m-d H:i:s', $article->PostTime),
            'view_nums'   => $article->ViewNums,
            'comm_nums'   => $article->CommNums,
            'url'         => $article->Url,
            'tags'        => $article->Tag,
        ];
    }

    return [
        'content' => [
            [
                'type' => 'text',
                'text' => json_encode([
                    'page'     => $page,
                    'count'    => count($list),
                    'articles' => $list,
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ],
        ],
    ];
}

/**
 * 工具：获取单篇文章详情
 */
function zblogmcp_tool_get_article($args)
{
    global $zbp;

    $id = isset($args['id']) ? (int) $args['id'] : 0;
    if ($id <= 0) {
        throw new Exception('文章ID不能为空');
    }

    $article = $zbp->GetPostByID($id);
    if (!$article || $article->ID == 0) {
        throw new Exception("文章ID {$id} 不存在");
    }

    $data = [
        'id'          => $article->ID,
        'title'       => $article->Title,
        'content'     => $article->Content,
        'intro'       => $article->Intro,
        'cate_id'     => $article->CateID,
        'cate_name'   => $article->Category ? $article->Category->Name : '',
        'author_id'   => $article->AuthorID,
        'author_name' => $article->Author ? $article->Author->Name : '',
        'status'      => $article->Status,
        'post_time'   => date('Y-m-d H:i:s', $article->PostTime),
        'update_time' => $article->UpdateTime ? date('Y-m-d H:i:s', $article->UpdateTime) : '',
        'view_nums'   => $article->ViewNums,
        'comm_nums'   => $article->CommNums,
        'url'         => $article->Url,
        'tags'        => $article->Tag,
        'alias'       => $article->Alias,
        'is_top'      => $article->IsTop,
        'meta'        => $article->Metas ? $article->Metas->GetData() : [],
        'meta_schema' => zblogmcp_get_article_meta_schema_text(),
    ];

    return [
        'content' => [
            [
                'type' => 'text',
                'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ],
        ],
    ];
}

/**
 * 工具：创建或更新文章
 */
function zblogmcp_tool_write_article($args)
{
    global $zbp, $zblogmcp_config;

    $id = isset($args['id']) ? (int) $args['id'] : 0;
    $title = isset($args['title']) ? trim($args['title']) : '';
    $content = isset($args['content']) ? $args['content'] : '';

    if (empty($title)) {
        throw new Exception('文章标题不能为空');
    }
    if (empty($content)) {
        throw new Exception('文章内容不能为空');
    }

    $article = new Post();
    $is_update = false;
    $pre_tag = '';

    if ($id > 0) {
        $article = $zbp->GetPostByID($id);
        if (!$article || $article->ID == 0) {
            throw new Exception("文章ID {$id} 不存在");
        }
        $is_update = true;
    }
    $pre_tag = $article->Tag;

    $article->Title = $title;
    $article->Content = $content;
    $article->Type = ZC_POST_TYPE_ARTICLE;

    # 摘要
    if (isset($args['intro']) && $args['intro'] !== '') {
        $article->Intro = $args['intro'];
    } else {
        $article->Intro = mb_substr(strip_tags($content), 0, 200);
    }

    # 分类
    if (isset($args['cate_id'])) {
        $article->CateID = (int) $args['cate_id'];
    }

    # 标签
    $tag_changed = isset($args['tag']);
    if (isset($args['tag'])) {
        $article->Tag = zblogmcp_convert_tag_names_to_id_string($args['tag'], ZC_POST_TYPE_ARTICLE);
    }

    # 状态
    $default_status = isset($zblogmcp_config->default_write_status) ? (int) $zblogmcp_config->default_write_status : ZC_POST_STATUS_AUDITING;
    $requested_status = isset($args['status']) ? (int) $args['status'] : $default_status;
    $article->Status = $requested_status;

    # 别名
    if (isset($args['alias'])) {
        $article->Alias = $args['alias'];
    }

    # 作者
    $article->AuthorID = isset($args['author_id']) ? (int) $args['author_id'] : 1;

    zblogmcp_apply_article_extras($article, $args);

    # 时间
    if (!$is_update) {
        $article->PostTime = time();
    }
    $article->UpdateTime = time();

    $article->Save();
    $zbp->AddCache($article);

    # 更新统计
    if ($zbp->option['ZC_LARGE_DATA'] == false) {
        CountPost($article, null);
    }

    if ($tag_changed) {
        zblogmcp_sync_article_tag_counts($pre_tag, $article->Tag, $article->ID);
    }

    if (!$is_update) {
        # 新文章：更新标签和分类计数
        if ($article->CateID > 0) {
            CountCategoryArray([$article->CateID], +1);
        }
        if ($article->Status == ZC_POST_STATUS_PUBLIC) {
            CountNormalArticleNums(+1);
        }
        CountMemberArray([$article->AuthorID], [+1, 0, 0, 0]);
    }

    $zbp->AddBuildModule('previous');
    $zbp->AddBuildModule('calendar');
    $zbp->AddBuildModule('comments');
    $zbp->AddBuildModule('tags');
    $zbp->AddBuildModule('archives');
    $zbp->BuildModule();
    $zbp->SaveCache();

    $result = [
        'id'      => $article->ID,
        'title'   => $article->Title,
        'url'     => $article->Url,
        'action'  => $is_update ? 'updated' : 'created',
        'message' => $is_update ? '文章更新成功' : '文章创建成功',
    ];

    return [
        'content' => [
            [
                'type' => 'text',
                'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ],
        ],
    ];
}

/**
 * Convert submitted tag names to Z-Blog's internal tag ID string.
 */
function zblogmcp_convert_tag_names_to_id_string($tag_names, $post_type = ZC_POST_TYPE_ARTICLE)
{
    global $zbp;

    $tag_names = FormatString((string) $tag_names, '[noscript]');
    $tag_names = trim(strip_tags($tag_names));
    if ($tag_names === '' || $tag_names === ',') {
        return '';
    }

    if (preg_match('/^(?:\{\d+\})+$/', $tag_names)) {
        return $tag_names;
    }

    $tag_names = str_replace([';', "\xEF\xBC\x8C", "\xE3\x80\x81"], ',', $tag_names);
    $parts = explode(',', $tag_names);
    $names = [];
    foreach ($parts as $part) {
        $name = trim($part);
        if ($name !== '') {
            $names[] = $name;
        }
    }
    $names = array_slice(array_values(array_unique($names)), 0, 20);
    if (!$names) {
        return '';
    }

    $zbp->LoadTagsByNameString(implode(',', $names), $post_type);
    foreach ($names as $name) {
        if (isset($zbp->tagsbyname_type[$post_type][$name])) {
            continue;
        }

        $tag = new Tag();
        $tag->Name = $name;
        $tag->Type = $post_type;

        foreach ($GLOBALS['hooks']['Filter_Plugin_PostTag_Core'] as $fpname => &$fpsignal) {
            $fpname($tag);
        }

        FilterTag($tag);
        $tag->UpdateTime = time();
        $tag->Save();
        $zbp->AddCache($tag);
        $zbp->AddBuildModule('tags');

        foreach ($GLOBALS['hooks']['Filter_Plugin_PostTag_Succeed'] as $fpname => &$fpsignal) {
            $fpname($tag);
        }
    }

    $tag_string = '';
    foreach ($names as $name) {
        if (isset($zbp->tagsbyname_type[$post_type][$name])) {
            $tag_string .= '{' . $zbp->tagsbyname_type[$post_type][$name]->ID . '}';
        }
    }

    return $tag_string;
}

/**
 * Sync article tag counts using the same add/delete diff model as admin save.
 */
function zblogmcp_sync_article_tag_counts($pre_tag, $now_tag, $article_id)
{
    global $zbp;

    $pre_array = [];
    foreach ($zbp->LoadTagsByIDString($pre_tag) as $tag) {
        $pre_array[] = $tag->ID;
    }

    $now_array = [];
    foreach ($zbp->LoadTagsByIDString($now_tag) as $tag) {
        $now_array[] = $tag->ID;
    }

    $del_string = $zbp->ConvertTagIDtoString(array_diff($pre_array, $now_array));
    $add_string = $zbp->ConvertTagIDtoString(array_diff($now_array, $pre_array));

    if ($del_string) {
        CountTagArrayString($del_string, -1, $article_id);
    }
    if ($add_string) {
        CountTagArrayString($add_string, +1, $article_id);
    }
}

/**
 * Apply extra article fields and Meta payloads from MCP arguments.
 */
function zblogmcp_apply_article_extras($article, $args)
{
    if (!($article instanceof Post) || !is_array($args)) {
        return;
    }

    $data_info = $article->GetDataInfo();

    foreach (['Meta', 'meta', 'log_Meta'] as $meta_key) {
        if (array_key_exists($meta_key, $args)) {
            zblogmcp_apply_article_meta_payload($article, $args[$meta_key]);
        }
    }

    foreach ($args as $key => $value) {
        if (in_array($key, ['id', 'title', 'content', 'intro', 'cate_id', 'tag', 'status', 'alias', 'author_id', 'Meta', 'meta', 'log_Meta'], true)) {
            continue;
        }

        if (strpos($key, 'meta_') === 0) {
            zblogmcp_apply_article_meta_value($article, substr($key, 5), $value);
            continue;
        }

        if (strpos($key, 'log_') === 0) {
            $field = substr($key, 4);
            if (isset($data_info[$field])) {
                $article->$field = $value;
                continue;
            }
        }

        if (isset($data_info[$key])) {
            $article->$key = $value;
        }
    }
}

/**
 * Merge a Meta payload into the article Metas collection.
 */
function zblogmcp_apply_article_meta_payload($article, $payload)
{
    if (!($article instanceof Post) || !isset($article->Metas)) {
        return;
    }

    if (is_string($payload)) {
        $trimmed = trim($payload);
        if ($trimmed === '') {
            return;
        }

        $json = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            $payload = $json;
        } else {
            $unserialized = @unserialize($trimmed);
            if ($unserialized === false && $trimmed !== 'b:0;') {
                return;
            }
            if (is_array($unserialized)) {
                $payload = $unserialized;
            } else {
                return;
            }
        }
    } elseif (is_object($payload)) {
        $payload = get_object_vars($payload);
    }

    if (!is_array($payload)) {
        return;
    }

    foreach ($payload as $key => $value) {
        zblogmcp_apply_article_meta_value($article, $key, $value);
    }
}

/**
 * Set or delete one Meta key on the article.
 */
function zblogmcp_apply_article_meta_value($article, $name, $value)
{
    if (!($article instanceof Post) || !isset($article->Metas)) {
        return;
    }

    $name = trim((string) $name);
    if ($name === '') {
        return;
    }

    if ($value === null || $value === '') {
        $article->Metas->Del($name);
        return;
    }

    $article->Metas->$name = $value;
}

/**
 * Render article meta schema config for AI clients.
 */
function zblogmcp_get_article_meta_schema_text()
{
    global $zblogmcp_config;

    $schema = isset($zblogmcp_config->article_meta_schema) ? trim((string) $zblogmcp_config->article_meta_schema) : '';
    if ($schema === '') {
        // AI fallback for undocumented article fields.
        return '未配置具体说明时，请根据字段名、字段值格式以及当前主题/插件上下文推断文章扩展字段含义。仅在推断明确时写入或修改；无法确认时保留原值，并在结果中说明。';
    }

    $lines = preg_split('/\r\n|\r|\n/', $schema);
    $items = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $items[] = $line;
    }

    return implode("\n", $items);
}

/**
 * Tool: list categories.
 */
function zblogmcp_tool_list_categories($args)
{
    global $zbp;

    $categories = $zbp->GetCategoryList();
    $list = [];
    foreach ($categories as $cate) {
        $list[] = [
            'id'        => $cate->ID,
            'name'      => $cate->Name,
            'alias'     => $cate->Alias,
            'parent_id' => $cate->ParentID,
            'count'     => $cate->Count,
            'order'     => $cate->Order,
            'url'       => $cate->Url,
        ];
    }

    return [
        'content' => [
            [
                'type' => 'text',
                'text' => json_encode([
                    'total'      => count($list),
                    'categories' => $list,
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ],
        ],
    ];
}

/**
 * 工具：创建分类
 */
function zblogmcp_tool_create_category($args)
{
    global $zbp;

    $name = isset($args['name']) ? trim($args['name']) : '';
    if (empty($name)) {
        throw new Exception('分类名称不能为空');
    }

    # 检查是否已存在同名分类
    $existing = $zbp->GetCategoryByName($name);
    if ($existing && $existing->ID > 0) {
        throw new Exception("分类「{$name}」已存在（ID: {$existing->ID}）");
    }

    $cate = new Category();
    $cate->Name = $name;
    $cate->Type = ZC_POST_TYPE_ARTICLE;

    if (isset($args['alias']) && $args['alias'] !== '') {
        $cate->Alias = $args['alias'];
    }

    if (isset($args['parent_id']) && (int) $args['parent_id'] > 0) {
        $cate->ParentID = (int) $args['parent_id'];
    }

    if (isset($args['template']) && $args['template'] !== '') {
        $cate->Template = $args['template'];
    }

    $cate->UpdateTime = time();
    $cate->Save();

    $zbp->LoadCategories();
    $zbp->AddBuildModule('catalog');
    $zbp->BuildModule();
    $zbp->SaveCache();

    $result = [
        'id'      => $cate->ID,
        'name'    => $cate->Name,
        'alias'   => $cate->Alias,
        'url'     => $cate->Url,
        'message' => '分类创建成功',
    ];

    return [
        'content' => [
            [
                'type' => 'text',
                'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ],
        ],
    ];
}

function zblogmcp_tool_result($data)
{
    return [
        'content' => [
            [
                'type' => 'text',
                'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ],
        ],
    ];
}

function zblogmcp_page_args($args)
{
    $count = isset($args['count']) ? (int) $args['count'] : 20;
    $count = min(max($count, 1), 50);
    $page = isset($args['page']) ? max((int) $args['page'], 1) : 1;
    return [$count, $page, ($page - 1) * $count];
}

function zblogmcp_tag_data($tag)
{
    return [
        'id' => (int) $tag->ID,
        'name' => $tag->Name,
        'alias' => $tag->Alias,
        'type' => (int) $tag->Type,
        'count' => (int) $tag->Count,
        'intro' => $tag->Intro,
        'url' => $tag->Url,
    ];
}

function zblogmcp_tool_list_tags($args)
{
    global $zbp;
    list($count, $page, $offset) = zblogmcp_page_args($args);
    $type = isset($args['type']) ? (int) $args['type'] : 0;
    $where = [['=', 'tag_Type', $type]];
    if (!empty($args['search'])) {
        $where[] = ['search', 'tag_Name', trim($args['search'])];
    }
    $tags = $zbp->GetTagList('*', $where, ['tag_Count' => 'DESC', 'tag_ID' => 'ASC'], [$offset, $count], null);
    $list = [];
    foreach ($tags as $tag) {
        $list[] = zblogmcp_tag_data($tag);
    }
    return zblogmcp_tool_result(['page' => $page, 'count' => count($list), 'has_more' => count($list) == $count, 'tags' => $list]);
}

function zblogmcp_tool_get_tag($args)
{
    global $zbp;
    $tag = null;
    if (!empty($args['id'])) {
        $tag = $zbp->GetTagByID((int) $args['id']);
    } elseif (!empty($args['name'])) {
        $tag = $zbp->GetTagByName(trim($args['name']), isset($args['type']) ? (int) $args['type'] : 0);
    }
    if (!$tag || $tag->ID == 0) {
        throw new Exception('标签不存在');
    }
    return zblogmcp_tool_result(zblogmcp_tag_data($tag));
}

function zblogmcp_tool_save_tag($args)
{
    global $zbp;
    $name = isset($args['name']) ? trim($args['name']) : '';
    if ($name === '') {
        throw new Exception('标签名称不能为空');
    }
    $type = isset($args['type']) ? (int) $args['type'] : 0;
    $id = isset($args['id']) ? (int) $args['id'] : 0;
    $tag = $id > 0 ? $zbp->GetTagByID($id) : new Tag();
    if (!$tag || ($id > 0 && $tag->ID == 0)) {
        throw new Exception('标签不存在');
    }
    $existing = $zbp->GetTagByName($name, $type);
    if ($existing && $existing->ID > 0 && $existing->ID != $id) {
        throw new Exception("标签{$name}已存在");
    }
    $tag->Name = $name;
    $tag->Type = $type;
    if (isset($args['alias'])) {
        $tag->Alias = trim($args['alias']);
    }
    if (isset($args['intro'])) {
        $tag->Intro = $args['intro'];
    }
    FilterTag($tag);
    $tag->UpdateTime = time();
    CountTag($tag);
    $tag->Save();
    $zbp->AddCache($tag);
    $zbp->AddBuildModule('tags');
    $zbp->BuildModule();
    $zbp->SaveCache();
    return zblogmcp_tool_result(['message' => $id > 0 ? '标签更新成功' : '标签创建成功', 'tag' => zblogmcp_tag_data($tag)]);
}

function zblogmcp_tool_delete_tag($args)
{
    global $zbp;
    $tag = $zbp->GetTagByID(isset($args['id']) ? (int) $args['id'] : 0);
    if (!$tag || $tag->ID == 0) {
        throw new Exception('标签不存在');
    }
    $tag->Del();
    $zbp->DelItemToNavbar('tag', $tag->ID);
    $zbp->AddBuildModule('tags');
    $zbp->BuildModule();
    $zbp->SaveCache();
    return zblogmcp_tool_result(['message' => '标签删除成功', 'id' => (int) $tag->ID]);
}

function zblogmcp_media_data($upload)
{
    return [
        'id' => (int) $upload->ID,
        'name' => $upload->Name,
        'source_name' => $upload->SourceName,
        'mime_type' => $upload->MimeType,
        'size' => (int) $upload->Size,
        'author_id' => (int) $upload->AuthorID,
        'post_id' => (int) $upload->LogID,
        'intro' => $upload->Intro,
        'post_time' => date('Y-m-d H:i:s', (int) $upload->PostTime),
        'url' => $upload->Url,
    ];
}

function zblogmcp_tool_list_media($args)
{
    global $zbp;
    list($count, $page, $offset) = zblogmcp_page_args($args);
    $where = [];
    if (!empty($args['author_id'])) {
        $where[] = ['=', 'ul_AuthorID', (int) $args['author_id']];
    }
    if (!empty($args['post_id'])) {
        $where[] = ['=', 'ul_LogID', (int) $args['post_id']];
    }
    $uploads = $zbp->GetUploadList('*', $where, ['ul_PostTime' => 'DESC'], [$offset, $count], null);
    $list = [];
    foreach ($uploads as $upload) {
        $list[] = zblogmcp_media_data($upload);
    }
    return zblogmcp_tool_result(['page' => $page, 'count' => count($list), 'has_more' => count($list) == $count, 'media' => $list]);
}

function zblogmcp_tool_get_media($args)
{
    global $zbp;
    $upload = $zbp->GetUploadByID(isset($args['id']) ? (int) $args['id'] : 0);
    if (!$upload || $upload->ID == 0) {
        throw new Exception('媒体文件不存在');
    }
    return zblogmcp_tool_result(zblogmcp_media_data($upload));
}

function zblogmcp_upload_name($name)
{
    $name = basename(str_replace('\\', '/', trim($name)));
    if ($name === '' || $name === '.' || $name === '..') {
        throw new Exception('文件名不能为空');
    }
    return $name;
}

function zblogmcp_tool_upload_media($args)
{
    global $zbp;
    $name = zblogmcp_upload_name(isset($args['name']) ? $args['name'] : '');
    $data = isset($args['data']) ? trim($args['data']) : '';
    if (preg_match('/^data:[^;]+;base64,(.*)$/s', $data, $matches)) {
        $data = $matches[1];
    }
    $data = preg_replace('/\s+/', '', $data);
    $binary = base64_decode($data, true);
    if ($binary === false || $binary === '') {
        throw new Exception('媒体数据不是有效的Base64');
    }

    $upload = new Upload();
    $upload->Name = $name;
    $upload->SourceName = !empty($args['source_name']) ? $args['source_name'] : $name;
    $upload->MimeType = !empty($args['mime_type']) ? $args['mime_type'] : 'application/octet-stream';
    $upload->Size = strlen($binary);
    $upload->AuthorID = !empty($args['author_id']) ? (int) $args['author_id'] : 1;
    $upload->LogID = !empty($args['post_id']) ? (int) $args['post_id'] : 0;
    $upload->Intro = isset($args['intro']) ? $args['intro'] : '';
    if (!$upload->CheckExtName()) {
        throw new Exception('文件扩展名不在站点允许范围内');
    }
    if (!$upload->CheckSize()) {
        throw new Exception('文件大小超过站点上传限制');
    }
    $duplicate = $zbp->GetUploadList('*', [
        ['=', 'ul_Name', $upload->Name],
        ['>=', 'ul_PostTime', strtotime(date('Y-m-01'))],
        ['<=', 'ul_PostTime', strtotime(date('Y-m-t 23:59:59'))],
    ]);
    if (count($duplicate) > 0) {
        throw new Exception('本月已存在同名媒体文件');
    }
    if ($upload->SaveBase64File($data) !== true) {
        throw new Exception('媒体文件保存失败');
    }
    $upload->Save();
    $zbp->AddCache($upload);
    CountMemberArray([$upload->AuthorID], [0, 0, 0, +1]);
    return zblogmcp_tool_result(['message' => '媒体上传成功', 'media' => zblogmcp_media_data($upload)]);
}

function zblogmcp_tool_delete_media($args)
{
    global $zbp;
    $upload = $zbp->GetUploadByID(isset($args['id']) ? (int) $args['id'] : 0);
    if (!$upload || $upload->ID == 0) {
        throw new Exception('媒体文件不存在');
    }
    $upload->Del();
    CountMemberArray([$upload->AuthorID], [0, 0, 0, -1]);
    $upload->DelFile();
    return zblogmcp_tool_result(['message' => '媒体删除成功', 'id' => (int) $upload->ID]);
}

function zblogmcp_comment_data($comment)
{
    $post = $comment->Post;
    return [
        'id' => (int) $comment->ID,
        'post_id' => (int) $comment->LogID,
        'post_title' => $post && $post->ID > 0 ? $post->Title : '',
        'parent_id' => (int) $comment->ParentID,
        'root_id' => (int) $comment->RootID,
        'is_checking' => (int) $comment->IsChecking,
        'author_id' => (int) $comment->AuthorID,
        'name' => $comment->Name,
        'content' => $comment->Content,
        'email' => $comment->Email,
        'homepage' => $comment->HomePage,
        'post_time' => date('Y-m-d H:i:s', (int) $comment->PostTime),
    ];
}

function zblogmcp_tool_list_comments($args)
{
    global $zbp;
    list($count, $page, $offset) = zblogmcp_page_args($args);
    $where = [];
    if (!empty($args['post_id'])) {
        $where[] = ['=', 'comm_LogID', (int) $args['post_id']];
    }
    if (isset($args['is_checking'])) {
        $where[] = ['=', 'comm_IsChecking', (int) $args['is_checking']];
    }
    if (isset($args['root_id'])) {
        $where[] = ['=', 'comm_RootID', (int) $args['root_id']];
    }
    if (isset($args['parent_id'])) {
        $where[] = ['=', 'comm_ParentID', (int) $args['parent_id']];
    }
    $comments = $zbp->GetCommentList('*', $where, ['comm_PostTime' => 'DESC'], [$offset, $count], null);
    $list = [];
    foreach ($comments as $comment) {
        $list[] = zblogmcp_comment_data($comment);
    }
    return zblogmcp_tool_result(['page' => $page, 'count' => count($list), 'has_more' => count($list) == $count, 'comments' => $list]);
}

function zblogmcp_tool_get_comment($args)
{
    global $zbp;
    $comment = $zbp->GetCommentByID(isset($args['id']) ? (int) $args['id'] : 0);
    if (!$comment || $comment->ID == 0) {
        throw new Exception('评论不存在');
    }
    return zblogmcp_tool_result(zblogmcp_comment_data($comment));
}

function zblogmcp_tool_create_comment($args)
{
    global $zbp;
    $post = $zbp->GetPostByID(isset($args['post_id']) ? (int) $args['post_id'] : 0);
    if (!$post || $post->ID == 0) {
        throw new Exception('文章不存在');
    }
    $content = trim(FormatString(isset($args['content']) ? $args['content'] : '', '[nohtml]'));
    if ($content === '') {
        throw new Exception('评论内容不能为空');
    }
    $comment = new Comment();
    $comment->LogID = $post->ID;
    $comment->Name = trim(strip_tags(isset($args['name']) ? $args['name'] : 'MCP'));
    $comment->Name = $comment->Name === '' ? 'MCP' : mb_substr($comment->Name, 0, 50);
    $comment->Content = mb_substr($content, 0, 1000);
    $comment->Email = isset($args['email']) ? trim($args['email']) : '';
    $comment->HomePage = isset($args['homepage']) ? trim($args['homepage']) : '';
    $comment->AuthorID = 0;
    $comment->IP = zblogmcp_get_client_ip();
    $comment->Agent = 'zblogMcp';
    $comment->PostTime = time();
    $comment->IsChecking = isset($args['is_checking']) ? (int) $args['is_checking'] : 1;
    $parent_id = isset($args['parent_id']) ? (int) $args['parent_id'] : 0;
    if ($parent_id > 0) {
        $parent = $zbp->GetCommentByID($parent_id);
        if (!$parent || $parent->ID == 0 || $parent->LogID != $post->ID) {
            throw new Exception('父评论不存在或不属于当前文章');
        }
        $comment->ParentID = $parent->ID;
        $comment->RootID = $parent->RootID > 0 ? $parent->RootID : $parent->ID;
    }
    $comment->Save();
    $zbp->AddCache($comment);
    if ($comment->IsChecking) {
        CountCommentNums(0, +1);
    } else {
        CountPostArray([$comment->LogID], +1);
        CountCommentNums(+1, 0);
    }
    $zbp->AddBuildModule('comments');
    $zbp->BuildModule();
    $zbp->SaveCache();
    return zblogmcp_tool_result(['message' => '评论创建成功', 'comment' => zblogmcp_comment_data($comment)]);
}

function zblogmcp_tool_review_comment($args)
{
    global $zbp;
    $comment = $zbp->GetCommentByID(isset($args['id']) ? (int) $args['id'] : 0);
    if (!$comment || $comment->ID == 0) {
        throw new Exception('评论不存在');
    }
    $is_checking = !empty($args['is_checking']) ? 1 : 0;
    $original = (int) $comment->IsChecking;
    $comment->IsChecking = $is_checking;
    $comment->Save();
    if ($original != $is_checking) {
        if ($is_checking) {
            CountPostArray([$comment->LogID], -1);
            CountCommentNums(0, +1);
        } else {
            CountPostArray([$comment->LogID], +1);
            CountCommentNums(0, -1);
        }
    }
    $zbp->AddBuildModule('comments');
    $zbp->BuildModule();
    $zbp->SaveCache();
    return zblogmcp_tool_result(['message' => $is_checking ? '评论已设为待审核' : '评论审核已通过', 'comment' => zblogmcp_comment_data($comment)]);
}

function zblogmcp_delete_comment_tree($comment)
{
    global $zbp;
    $children = $zbp->GetCommentList('*', [['=', 'comm_ParentID', $comment->ID]]);
    foreach ($children as $child) {
        zblogmcp_delete_comment_tree($child);
    }
    if ($comment->IsChecking) {
        CountCommentNums(-1, -1);
    } else {
        CountPostArray([$comment->LogID], -1);
        CountCommentNums(-1, 0);
    }
    $comment->Del();
}

function zblogmcp_tool_delete_comment($args)
{
    global $zbp;
    $comment = $zbp->GetCommentByID(isset($args['id']) ? (int) $args['id'] : 0);
    if (!$comment || $comment->ID == 0) {
        throw new Exception('评论不存在');
    }
    zblogmcp_delete_comment_tree($comment);
    $zbp->AddBuildModule('comments');
    $zbp->BuildModule();
    $zbp->SaveCache();
    return zblogmcp_tool_result(['message' => '评论删除成功', 'id' => (int) $comment->ID]);
}

# ========== JSON-RPC 响应函数 ==========

/**
 * 输出成功响应
 */
function zblogmcp_success_response($id, $result)
{
    $response = [
        'jsonrpc' => '2.0',
        'id'      => $id,
        'result'  => $result,
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 输出错误响应
 */
function zblogmcp_error_response($id, $code, $message)
{
    $response = [
        'jsonrpc' => '2.0',
        'id'      => $id,
        'error'   => [
            'code'    => $code,
            'message' => $message,
        ],
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 记录 MCP 请求日志
 */
function zblogmcp_log_request($request, $result, $api_key_record, $start_time, $status_code)
{
    $elapsed = (int) ((microtime(true) - $start_time) * 1000);
    $method = isset($request['method']) ? $request['method'] : 'unknown';
    $endpoint = '';

    # 如果是 tools/call，记录具体工具名
    if ($method === 'tools/call' && isset($request['params']['name'])) {
        $endpoint = $request['params']['name'];
    }

    zblogmcp_add_log([
        'method'        => $method,
        'endpoint'      => $endpoint,
        'request_body'  => json_encode($request, JSON_UNESCAPED_UNICODE),
        'response_body' => is_array($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : (string) $result,
        'status_code'   => $status_code,
        'response_time' => $elapsed,
        'ip'            => zblogmcp_get_client_ip(),
        'api_key_id'    => $api_key_record ? (int) $api_key_record['id'] : 0,
    ]);
}
