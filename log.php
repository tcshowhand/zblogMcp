<?php
/**
 * zblogMcp - MCP 日志查看页（Vue3 + Element Plus）
 */
require_once __DIR__ . '/header.php';

if (GetVars('act', 'GET') == 'del') {
    CheckIsRefererValid();
    zblogmcp_del_log(GetVars('id', 'GET'));
}
if (GetVars('act', 'GET') == 'clear') {
    CheckIsRefererValid();
    zblogmcp_clear_log();
}
if (GetVars('act', 'GET') == 'clean_old') {
    CheckIsRefererValid();
    zblogmcp_clean_old_logs();
    zblogmcp_success('已清理超过 ' . $zblogmcp_config->log_max_days . ' 天的旧日志');
}

$stats = zblogmcp_log_stats();
$log_result = zblogmcp_log_list();
$logs = $log_result['log'];
$p = $log_result['p'];

$keys = zblogmcp_key_list();
$key_name_map = [];
foreach ($keys as $k) {
    $key_name_map[$k['id']] = $k['name'];
}

$logs_json = json_encode($logs, JSON_UNESCAPED_UNICODE);
$stats_json = json_encode($stats, JSON_UNESCAPED_UNICODE);
$key_map_json = json_encode($key_name_map, JSON_UNESCAPED_UNICODE);
$page_now = $p->PageNow;
$page_count = $p->PageCount;
$total = $p->Count;
$log_max_days = (int) $zblogmcp_config->log_max_days;
$csrf_token = $zbp->GetCSRFToken();
?>
<div id="divMain" class="plugin-area">
    <div class="divHeader"><?php echo $zblogmcp_plugin_info['name']; ?></div>
    <div class="SubMenu"><?php echo zblogmcp_SubMenu(); ?></div>
    <div id="divMain2">
        <div id="logApp" class="zblogmcp-app">

            <!-- 统计卡片 -->
            <el-row :gutter="16" class="stats-row">
                <el-col :span="6">
                    <el-card shadow="hover">
                        <el-statistic title="总请求数" :value="stats.total"></el-statistic>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="hover">
                        <el-statistic title="成功" :value="stats.success">
                            <template #suffix><span style="color:#67c23a; font-size:14px;">成功</span></template>
                        </el-statistic>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="hover">
                        <el-statistic title="失败" :value="stats.fail">
                            <template #suffix><span style="color:#f56c6c; font-size:14px;">失败</span></template>
                        </el-statistic>
                    </el-card>
                </el-col>
                <el-col :span="6">
                    <el-card shadow="hover">
                        <el-statistic title="今日请求" :value="stats.today"></el-statistic>
                    </el-card>
                </el-col>
            </el-row>

            <!-- 操作栏 -->
            <el-card shadow="never" style="margin-bottom:15px;">
                <el-button type="warning" plain @click="cleanOld">
                    <i class="fa-solid fa-broom" aria-hidden="true"></i>
                    清理 {{ logMaxDays }} 天前日志
                </el-button>
                <el-button type="danger" plain @click="clearAll">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                    清空全部日志
                </el-button>
            </el-card>

            <!-- 日志表格 -->
            <el-card shadow="never">
                <el-table :data="logs" style="width:100%" stripe border>
                    <el-table-column prop="id" label="ID" width="60" align="center"></el-table-column>
                    <el-table-column label="方法" min-width="120">
                        <template #default="{ row }">
                            <el-tag size="small" effect="plain"><code>{{ row.method }}</code></el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="工具/端点" min-width="140" show-overflow-tooltip>
                        <template #default="{ row }">
                            <code v-if="row.endpoint">{{ row.endpoint }}</code>
                            <span v-else style="color:#999;">-</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="70" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.status_code == 1 ? 'success' : 'danger'" size="small">
                                {{ row.status_code == 1 ? '成功' : '失败' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="耗时" width="80" align="center">
                        <template #default="{ row }">
                            <el-text size="small">{{ row.response_time }}ms</el-text>
                        </template>
                    </el-table-column>
                    <el-table-column label="IP" min-width="110">
                        <template #default="{ row }">
                            <el-text size="small">{{ row.ip }}</el-text>
                        </template>
                    </el-table-column>
                    <el-table-column label="ApiKey" min-width="90">
                        <template #default="{ row }">
                            <span v-if="row.api_key_id > 0 && keyMap[row.api_key_id]">
                                {{ keyMap[row.api_key_id] }}
                            </span>
                            <span v-else style="color:#999;">-</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="时间" min-width="100" align="center">
                        <template #default="{ row }">
                            <el-text size="small">{{ formatTime(row.create_time) }}</el-text>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="150" align="center" fixed="right">
                        <template #default="{ row }">
                            <div class="zblogmcp-action-buttons">
                                <el-button size="small" @click="showDetail(row)">
                                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>详情</span>
                                </el-button>
                                <el-button type="danger" size="small" @click="confirmDelete(row)">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i><span>删除</span>
                                </el-button>
                            </div>
                        </template>
                    </el-table-column>
                    <template #empty>
                        <el-empty description="暂无 MCP 请求日志"></el-empty>
                    </template>
                </el-table>

                <!-- 分页 -->
                <div style="margin-top:15px; display:flex; justify-content:flex-end;" v-if="total > 0">
                    <el-pagination
                        background
                        layout="total, prev, pager, next"
                        :total="total"
                        :page-size="pageCount"
                        :current-page="pageNow"
                        @current-change="goPage"
                    ></el-pagination>
                </div>
            </el-card>

            <!-- 详情弹窗 -->
            <el-dialog v-model="detailVisible" title="请求详情" width="700px" destroy-on-close>
                <template v-if="currentLog">
                    <el-descriptions :column="2" border size="small" style="margin-bottom:15px;">
                        <el-descriptions-item label="方法"><code>{{ currentLog.method }}</code></el-descriptions-item>
                        <el-descriptions-item label="工具/端点"><code>{{ currentLog.endpoint || '-' }}</code></el-descriptions-item>
                        <el-descriptions-item label="状态">
                            <el-tag :type="currentLog.status_code == 1 ? 'success' : 'danger'" size="small">
                                {{ currentLog.status_code == 1 ? '成功' : '失败' }}
                            </el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item label="耗时">{{ currentLog.response_time }}ms</el-descriptions-item>
                        <el-descriptions-item label="IP">{{ currentLog.ip }}</el-descriptions-item>
                        <el-descriptions-item label="时间">{{ currentLog.create_time }}</el-descriptions-item>
                    </el-descriptions>

                    <h4 style="margin:10px 0 5px;">请求内容</h4>
                    <div class="log-detail-json">{{ formatJson(currentLog.request_body) }}</div>

                    <h4 style="margin:10px 0 5px;">响应内容</h4>
                    <div class="log-detail-json">{{ formatJson(currentLog.response_body) }}</div>
                </template>
            </el-dialog>

        </div>
    </div>
</div>

<script>
const { createApp, ref } = Vue;

createApp({
    setup() {
        const logs = ref(<?php echo $logs_json; ?>);
        const stats = ref(<?php echo $stats_json; ?>);
        const keyMap = ref(<?php echo $key_map_json; ?>);
        const pageNow = ref(<?php echo $page_now; ?>);
        const pageCount = ref(<?php echo $page_count; ?>);
        const total = ref(<?php echo $total; ?>);
        const logMaxDays = ref(<?php echo $log_max_days; ?>);
        const csrfToken = '<?php echo $csrf_token; ?>';
        const detailVisible = ref(false);
        const currentLog = ref(null);

        const formatTime = (time) => {
            if (!time) return '-';
            return time.substring(5, 16);
        };

        const formatJson = (str) => {
            if (!str) return '(空)';
            try {
                return JSON.stringify(JSON.parse(str), null, 2);
            } catch(e) {
                return str;
            }
        };

        const goPage = (page) => {
            const url = new URL(location.href);
            url.searchParams.set('page', page);
            location.href = url.toString();
        };

        const showDetail = (row) => {
            currentLog.value = row;
            detailVisible.value = true;
        };

        const confirmDelete = (row) => {
            ElementPlus.ElMessageBox.confirm('确定删除此条日志？', '提示', {
                confirmButtonText: '确定',
                cancelButtonText: '取消',
                type: 'warning'
            }).then(() => {
                location.href = `log.php?act=del&id=${row.id}&csrfToken=${csrfToken}`;
            });
        };

        const cleanOld = () => {
            ElementPlus.ElMessageBox.confirm(`确定清理 ${logMaxDays.value} 天前的旧日志？`, '提示', {
                confirmButtonText: '确定',
                cancelButtonText: '取消',
                type: 'warning'
            }).then(() => {
                location.href = `log.php?act=clean_old&csrfToken=${csrfToken}`;
            });
        };

        const clearAll = () => {
            ElementPlus.ElMessageBox.confirm('确定清空全部日志？此操作不可恢复！', '警告', {
                confirmButtonText: '确定清空',
                cancelButtonText: '取消',
                type: 'error'
            }).then(() => {
                location.href = `log.php?act=clear&csrfToken=${csrfToken}`;
            });
        };

        return {
            logs, stats, keyMap, pageNow, pageCount, total, logMaxDays,
            detailVisible, currentLog,
            formatTime, formatJson, goPage, showDetail, confirmDelete, cleanOld, clearAll
        };
    }
}).use(ElementPlus).mount('#logApp');
</script>
<?php require_once __DIR__ . '/foot.php'; ?>
