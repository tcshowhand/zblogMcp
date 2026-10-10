<?php
/**
 * zblogMcp - plugin settings page.
 */
require_once __DIR__ . '/header.php';

$settings_tab = GetVars('tab', 'GET');
if (!in_array($settings_tab, ['plugin', 'integration'], true)) {
    $settings_tab = 'plugin';
}

if (GetVars('act', 'GET') == 'save') {
    CheckIsRefererValid();
    $zblogmcp_config->log_enabled = (int) GetVars('log_enabled', 'POST');
    $zblogmcp_config->log_body_enabled = (int) GetVars('log_body_enabled', 'POST');
    $zblogmcp_config->log_max_days = (int) GetVars('log_max_days', 'POST');
    $zblogmcp_config->default_write_status = (int) GetVars('default_write_status', 'POST');
    $zblogmcp_config->article_meta_schema = trim((string) GetVars('article_meta_schema', 'POST'));
    $zbp->SaveConfig('zblogMcp');
    zblogmcp_redirect('settings.php?tab=' . $settings_tab, '设置已保存');
}

$mcp_url = $bloghost . 'zb_users/plugin/zblogMcp/mcp.php';
$csrf_token = $zbp->GetCSRFToken();
$config_json = json_encode([
    'log_enabled' => (int) $zblogmcp_config->log_enabled,
    'log_body_enabled' => (int) $zblogmcp_config->log_body_enabled,
    'log_max_days' => (int) $zblogmcp_config->log_max_days,
    'default_write_status' => isset($zblogmcp_config->default_write_status) ? (int) $zblogmcp_config->default_write_status : ZC_POST_STATUS_AUDITING,
    'article_meta_schema' => isset($zblogmcp_config->article_meta_schema) ? (string) $zblogmcp_config->article_meta_schema : '',
], JSON_UNESCAPED_UNICODE);
?>
<div id="divMain" class="plugin-area">
    <div class="divHeader"><?php echo $zblogmcp_plugin_info['name']; ?></div>
    <div class="SubMenu"><?php echo zblogmcp_SubMenu(); ?></div>
    <div id="divMain2">
        <div id="settingsApp" class="zblogmcp-app">
            <?php if ($settings_tab === 'plugin') : ?>
                <div class="settings-panel plugin-settings-grid">
                    <el-card shadow="never">
                        <template #header>
                            <span style="font-size:16px; font-weight:bold;">日志设置</span>
                        </template>
                        <el-form :model="form" label-width="140px" size="large">
                            <el-form-item label="记录 MCP 请求日志">
                                <el-switch
                                    v-model="form.log_enabled"
                                    :active-value="1"
                                    :inactive-value="0"
                                    active-text="开启"
                                    inactive-text="关闭"
                                ></el-switch>
                            </el-form-item>
                            <el-form-item label="日志保留天数">
                                <el-input-number v-model="form.log_max_days" :min="1" :max="365" style="width:150px;"></el-input-number>
                                <span style="margin-left:10px; color:#999; font-size:13px;">超过天数的日志将在清理时自动删除</span>
                            </el-form-item>
                        </el-form>
                    </el-card>

                    <el-card shadow="never">
                        <template #header>
                            <span style="font-size:16px; font-weight:bold;">MCP 安全配置</span>
                        </template>
                        <el-form :model="form" label-width="170px" size="large">
                            <el-form-item label="记录请求/响应正文">
                                <el-switch v-model="form.log_body_enabled" :active-value="1" :inactive-value="0" active-text="开启" inactive-text="关闭"></el-switch>
                            </el-form-item>
                            <el-form-item label="文章默认写入状态">
                                <el-radio-group v-model="form.default_write_status">
                                    <el-radio :label="0">公开</el-radio>
                                    <el-radio :label="1">草稿</el-radio>
                                    <el-radio :label="2">审核</el-radio>
                                </el-radio-group>
                            </el-form-item>
                        </el-form>
                    </el-card>

                    <el-card shadow="never">
                        <template #header>
                            <span style="font-size:16px; font-weight:bold;">文章扩展字段说明</span>
                        </template>
                        <div style="margin-bottom:8px; color:#666; font-size:13px;">用于AI识别某些第三方插件或者主题自定义的文章扩展字段，如没有安装过类似的插件无需填写。每行一个字段，格式：字段名=说明。未填写时，AI 会根据字段名、值格式和主题/插件上下文自行推断，无法确认时不会擅自覆盖字段。</div>
                        <el-input
                            v-model="form.article_meta_schema"
                            type="textarea"
                            :rows="8"
                            placeholder="seo_title=SEO标题，用于搜索引擎展示&#10;cover_image=封面图，填写附件ID或图片URL&#10;source_url=原文链接，仅转载文章填写"
                        ></el-input>
                    </el-card>

                </div>
            <?php else : ?>
                <div class="settings-panel">
                    <el-card shadow="never" class="mcp-info-card">
                        <template #header>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <el-icon :size="18" color="#409eff"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 3L4 14h6l-1 7 9-11h-6l1-7z"/></svg></el-icon>
                                <span style="font-size:16px; font-weight:bold;">MCP 接入信息</span>
                            </div>
                        </template>

                        <el-descriptions :column="1" border>
                            <el-descriptions-item label="MCP 端点">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span class="mcp-url-text">{{ mcpUrl }}</span>
                                    <el-button size="small" @click="copyText(mcpUrl)">
                                        <i class="fa-solid fa-copy" aria-hidden="true"></i><span>复制</span>
                                    </el-button>
                                </div>
                            </el-descriptions-item>
                            <el-descriptions-item label="认证方式">
                                <el-tag size="small">Bearer Token</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="协议版本">
                                <el-tag size="small" type="info">2024-11-05</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="可用工具">
                                <div style="display:flex; flex-direction:column; gap:8px;">
                                    <div v-for="tool in tools" :key="tool.name" style="display:flex; align-items:flex-start; gap:8px;">
                                        <el-tag size="small" effect="plain"><code>{{ tool.name }}</code></el-tag>
                                        <span style="color:#606266; line-height:24px;">{{ tool.description }}</span>
                                    </div>
                                </div>
                            </el-descriptions-item>
                        </el-descriptions>
                    </el-card>

                    <el-card shadow="never" class="mcp-info-card" style="margin-top:15px;">
                        <template #header>
                            <span style="font-size:16px; font-weight:bold;">客户端配置示例</span>
                        </template>
                        <el-tabs v-model="clientTab">
                            <el-tab-pane label="Claude Desktop" name="claude">
                                <div style="margin-bottom:8px; color:#666; font-size:13px;">在 Claude Desktop 的配置文件中添加：</div>
                                <div class="log-detail-json" style="max-height:none;" v-text="claudeConfig"></div>
                            </el-tab-pane>
                            <el-tab-pane label="Cursor / VS Code" name="cursor">
                                <div style="margin-bottom:8px; color:#666; font-size:13px;">在 Cursor 或 VS Code 的 MCP 设置中添加：</div>
                                <div class="log-detail-json" style="max-height:none;" v-text="cursorConfig"></div>
                            </el-tab-pane>
                            <el-tab-pane label="WorkBuddy" name="workbuddy">
                                <div style="margin-bottom:8px; color:#666; font-size:13px;">在 WorkBuddy 的 MCP Server 配置中添加：</div>
                                <div class="log-detail-json" style="max-height:none;" v-text="workbuddyConfig"></div>
                            </el-tab-pane>
                            <el-tab-pane label="TareWork" name="tarework">
                                <div style="margin-bottom:8px; color:#666; font-size:13px;">在 TareWork 的 MCP 工具配置中添加：</div>
                                <div class="log-detail-json" style="max-height:none;" v-text="tareworkConfig"></div>
                            </el-tab-pane>
                            <el-tab-pane label="Qclaw" name="qclaw">
                                <div style="margin-bottom:8px; color:#666; font-size:13px;">在 Qclaw 的 MCP 服务配置中添加：</div>
                                <div class="log-detail-json" style="max-height:none;" v-text="qclawConfig"></div>
                            </el-tab-pane>
                            <el-tab-pane label="其他 AI 工具" name="other">
                                <div style="margin-bottom:8px; color:#666; font-size:13px;">支持 HTTP MCP Server 的 AI 工具可参考以下配置：</div>
                                <div class="log-detail-json" style="max-height:none;" v-text="otherConfig"></div>
                            </el-tab-pane>
                        </el-tabs>
                        <div style="margin-top:8px;">
                            <el-button size="small" @click="copyText(currentClientConfig())">
                                <i class="fa-solid fa-file-code" aria-hidden="true"></i><span>复制配置</span>
                            </el-button>
                        </div>
                    </el-card>

                    <el-card shadow="never" class="mcp-info-card" style="margin-top:15px;">
                        <template #header>
                            <span style="font-size:16px; font-weight:bold;">技能配置</span>
                        </template>
                        <div style="margin-bottom:8px; color:#666; font-size:13px;">可复制到支持“技能”或“自定义指令”的 AI 客户端，用于约束博客管理操作。</div>
                        <div class="log-detail-json" style="max-height:none; white-space:pre-wrap;" v-text="skillConfig"></div>
                        <div style="margin-top:8px;">
                            <el-button size="small" @click="copyText(skillConfig)">
                                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span>复制技能配置</span>
                            </el-button>
                        </div>
                    </el-card>
                </div>
            <?php endif; ?>
            <?php if ($settings_tab === 'plugin') : ?>
                <div class="settings-actions" style="margin-top:20px; text-align:left;">
                    <el-button type="primary" @click="submitForm" :loading="submitting">
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><span>保存设置</span>
                    </el-button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="settingsForm" method="post" action="settings.php?act=save&amp;tab=<?php echo urlencode($settings_tab); ?>" style="display:none;">
    <input type="hidden" name="log_enabled" :value="form.log_enabled">
    <input type="hidden" name="log_body_enabled" :value="form.log_body_enabled">
    <input type="hidden" name="log_max_days" :value="form.log_max_days">
    <input type="hidden" name="default_write_status" :value="form.default_write_status">
    <input type="hidden" name="article_meta_schema" :value="form.article_meta_schema">
    <input type="hidden" name="csrfToken" value="<?php echo $csrf_token; ?>">
</form>

<script>
const { createApp, ref, reactive } = Vue;

createApp({
    setup() {
        const config = <?php echo $config_json; ?>;
        const form = reactive({
            log_enabled: config.log_enabled,
            log_body_enabled: config.log_body_enabled,
            log_max_days: config.log_max_days,
            default_write_status: config.default_write_status,
            article_meta_schema: config.article_meta_schema
        });
        const submitting = ref(false);
        const clientTab = ref('claude');
        const mcpUrl = <?php echo json_encode($mcp_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const tools = [
            { name: 'zblog_list_articles', description: '查询文章列表，支持分类、关键词和状态筛选' },
            { name: 'zblog_get_article', description: '读取单篇文章的完整内容和元数据' },
            { name: 'zblog_write_article', description: '创建或更新博客文章' },
            { name: 'zblog_list_categories', description: '查询博客文章分类' },
            { name: 'zblog_create_category', description: '创建新的文章分类' },
            { name: 'zblog_list_tags', description: '查询博客标签及文章使用数量' },
            { name: 'zblog_get_tag', description: '读取单个标签详情' },
            { name: 'zblog_save_tag', description: '创建或更新博客标签' },
            { name: 'zblog_delete_tag', description: '删除博客标签' },
            { name: 'zblog_list_media', description: '查询媒体文件和附件' },
            { name: 'zblog_get_media', description: '读取单个媒体文件详情和访问地址' },
            { name: 'zblog_upload_media', description: '通过 Base64 上传图片或附件' },
            { name: 'zblog_delete_media', description: '删除媒体记录及对应文件' },
            { name: 'zblog_list_comments', description: '查询文章评论和审核状态' },
            { name: 'zblog_get_comment', description: '读取单条评论详情' },
            { name: 'zblog_create_comment', description: '为文章创建评论或回复' },
            { name: 'zblog_review_comment', description: '切换评论的审核状态' },
            { name: 'zblog_delete_comment', description: '删除评论及其子评论' }
        ];

        const claudeConfig = JSON.stringify({
            mcpServers: {
                zblog: {
                    url: mcpUrl,
                    headers: { Authorization: 'Bearer your_api_key_here' }
                }
            }
        }, null, 2);
        const cursorConfig = claudeConfig;
        const workbuddyConfig = cursorConfig;
        const tareworkConfig = cursorConfig;
        const qclawConfig = cursorConfig;
        const otherConfig = cursorConfig;
        const clientConfigs = { claude: claudeConfig, cursor: cursorConfig, workbuddy: workbuddyConfig, tarework: tareworkConfig, qclaw: qclawConfig, other: otherConfig };
        const skillConfig = [
            '# zblog mcp',
            '',
            '通过 zblog mcp 管理博客内容。默认先读取和确认目标，再执行写入、标签、媒体或评论操作。',
            '文章默认遵守插件设置中的写入状态；涉及发布、删除和评论审核时，先向用户确认。',
            '媒体上传使用 Base64 数据，禁止上传可执行脚本或未授权文件。'
        ].join('\n');

        const currentClientConfig = () => clientConfigs[clientTab.value] || claudeConfig;
        const copyText = (text) => {
            navigator.clipboard.writeText(text).then(() => {
                ElementPlus.ElMessage.success('已复制到剪贴板');
            }).catch(() => {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                ElementPlus.ElMessage.success('已复制到剪贴板');
            });
        };

        const submitForm = () => {
            submitting.value = true;
            const hiddenForm = document.getElementById('settingsForm');
            hiddenForm.querySelector('[name=log_enabled]').value = form.log_enabled;
            hiddenForm.querySelector('[name=log_body_enabled]').value = form.log_body_enabled;
            hiddenForm.querySelector('[name=log_max_days]').value = form.log_max_days;
            hiddenForm.querySelector('[name=default_write_status]').value = form.default_write_status;
            hiddenForm.querySelector('[name=article_meta_schema]').value = form.article_meta_schema;
            hiddenForm.submit();
        };

        return {
            form, submitting, clientTab, mcpUrl, tools,
            claudeConfig, cursorConfig, workbuddyConfig, tareworkConfig, qclawConfig, otherConfig,
            skillConfig, currentClientConfig, copyText, submitForm
        };
    }
}).use(ElementPlus).mount('#settingsApp');
</script>
<?php require_once __DIR__ . '/foot.php'; ?>
