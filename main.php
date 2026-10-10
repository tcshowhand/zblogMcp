<?php
/**
 * zblogMcp - ApiKey 管理主页（Vue3 + Element Plus）
 */
require_once __DIR__ . '/header.php';

# 处理删除操作
if (GetVars('act', 'GET') == 'del') {
    CheckIsRefererValid();
    zblogmcp_del_key(GetVars('id', 'GET'));
}

# 处理切换状态操作
if (GetVars('act', 'GET') == 'toggle') {
    CheckIsRefererValid();
    zblogmcp_toggle_key(GetVars('id', 'GET'), GetVars('status', 'GET'));
}

$keys = zblogmcp_key_list();
$keys_json = json_encode($keys, JSON_UNESCAPED_UNICODE);
$mcp_url = $bloghost . 'zb_users/plugin/zblogMcp/mcp.php';
$csrf_token = $zbp->GetCSRFToken();
?>
<div id="divMain" class="plugin-area">
    <div class="divHeader"><?php echo $zblogmcp_plugin_info['name']; ?></div>
    <div class="SubMenu"><?php echo zblogmcp_SubMenu(); ?></div>
    <div id="divMain2">
        <div id="keyApp" class="zblogmcp-app">
            <el-card shadow="never">
                <template #header>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:16px; font-weight:bold;">ApiKey 管理</span>
                        <el-button type="primary" @click="goAdd">
                            <i class="fa-solid fa-key" aria-hidden="true"></i><span>新增 ApiKey</span>
                        </el-button>
                    </div>
                </template>

                <el-table :data="keys" style="width:100%" stripe border>
                    <el-table-column prop="id" label="ID" width="60" align="center"></el-table-column>
                    <el-table-column prop="name" label="名称" min-width="130">
                        <template #default="{ row }">
                            <el-tag effect="plain">{{ row.name }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="ApiKey" min-width="280">
                        <template #default="{ row }">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="key-text" v-text="row.showKey ? row.api_key : maskKey(row.api_key)"></span>
                                <el-button size="small" text @click="row.showKey = !row.showKey">
                                    <i class="fa-solid" :class="row.showKey ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                                    {{ row.showKey ? '隐藏' : '显示' }}
                                </el-button>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="80" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.status == 1 ? 'success' : 'danger'" size="small">
                                {{ row.status == 1 ? '启用' : '禁用' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="remark" label="备注" min-width="80" show-overflow-tooltip></el-table-column>
                    <el-table-column label="创建时间" min-width="110" align="center">
                        <template #default="{ row }">{{ formatTime(row.create_time) }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="480" align="center" fixed="right">
                        <template #default="{ row }">
                            <div class="zblogmcp-action-buttons">
                                <el-button type="primary" plain size="small" @click="copyKey(row.api_key)">
                                    <i class="fa-solid fa-copy" aria-hidden="true"></i><span>复制</span>
                                </el-button>
                                <el-button type="success" plain size="small" @click="copyClientConfig(row)">
                                    <i class="fa-solid fa-file-code" aria-hidden="true"></i><span>复制配置</span>
                                </el-button>
                                <el-button size="small" @click="goEdit(row.id)">
                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span>编辑</span>
                                </el-button>
                                <el-button
                                    :type="row.status == 1 ? 'warning' : 'success'"
                                    size="small"
                                    @click="toggleStatus(row)"
                                >
                                    <i class="fa-solid" :class="row.status == 1 ? 'fa-ban' : 'fa-circle-check'" aria-hidden="true"></i>
                                    <span>{{ row.status == 1 ? '禁用' : '启用' }}</span>
                                </el-button>
                                <el-button type="danger" size="small" @click="confirmDelete(row)">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i><span>删除</span>
                                </el-button>
                            </div>
                        </template>
                    </el-table-column>
                    <template #empty>
                        <el-empty description="暂无 ApiKey 记录">
                            <el-button type="primary" @click="goAdd">
                                <i class="fa-solid fa-key" aria-hidden="true"></i><span>立即创建</span>
                            </el-button>
                        </el-empty>
                    </template>
                </el-table>
            </el-card>

        </div>
    </div>
</div>

<script>
const { createApp, ref } = Vue;

createApp({
    setup() {
        const keys = ref(<?php echo $keys_json; ?>.map(k => ({ ...k, showKey: false })));
        const mcpUrl = <?php echo json_encode($mcp_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const csrfToken = '<?php echo $csrf_token; ?>';

        const maskKey = (key) => {
            if (!key) return '';
            return key.length > 4 ? '*'.repeat(key.length - 4) + key.slice(-4) : '*'.repeat(key.length);
        };

        const formatTime = (time) => {
            if (!time) return '-';
            return time.substring(0, 16);
        };

        const goAdd = () => { location.href = 'key_edit.php'; };
        const goEdit = (id) => { location.href = 'key_edit.php?id=' + id; };

        const copyText = async (text, successMessage) => {
            if (!text) {
                ElementPlus.ElMessage.warning('内容为空，无法复制');
                return;
            }
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.left = '-9999px';
                    textarea.style.top = '0';
                    document.body.appendChild(textarea);
                    textarea.focus();
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                }
                ElementPlus.ElMessage.success(successMessage);
            } catch (error) {
                ElementPlus.ElMessage.error('复制失败，请手动复制');
            }
        };

        const buildClientConfig = (apiKey) => JSON.stringify({
            mcpServers: {
                zblog: {
                    url: mcpUrl,
                    headers: { Authorization: `Bearer ${apiKey}` }
                }
            }
        }, null, 2);

        const copyKey = (key) => copyText(key, 'ApiKey 已复制');
        const copyClientConfig = (row) => {
            if (!row.api_key) {
                ElementPlus.ElMessage.warning('ApiKey 为空，无法生成配置');
                return;
            }
            copyText(buildClientConfig(row.api_key), 'MCP 客户端配置已复制');
        };

        const toggleStatus = (row) => {
            const action = row.status == 1 ? '禁用' : '启用';
            ElementPlus.ElMessageBox.confirm(`确定${action} ApiKey「${row.name}」？`, '提示', {
                confirmButtonText: '确定',
                cancelButtonText: '取消',
                type: 'warning'
            }).then(() => {
                location.href = `main.php?act=toggle&id=${row.id}&status=${row.status == 1 ? 0 : 1}&csrfToken=${csrfToken}`;
            });
        };

        const confirmDelete = (row) => {
            ElementPlus.ElMessageBox.confirm(`确定删除 ApiKey「${row.name}」？删除后不可恢复！`, '警告', {
                confirmButtonText: '确定删除',
                cancelButtonText: '取消',
                type: 'error'
            }).then(() => {
                location.href = `main.php?act=del&id=${row.id}&csrfToken=${csrfToken}`;
            });
        };

        return { keys, maskKey, formatTime, goAdd, goEdit, copyKey, copyClientConfig, toggleStatus, confirmDelete };
    }
}).use(ElementPlus).mount('#keyApp');
</script>
<?php require_once __DIR__ . '/foot.php'; ?>
