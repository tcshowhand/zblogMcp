<?php
/**
 * zblogMcp - about page.
 */
require_once __DIR__ . '/header.php';
?>
<div id="divMain" class="plugin-area">
    <div class="divHeader"><?php echo $zblogmcp_plugin_info['name']; ?></div>
    <div class="SubMenu"><?php echo zblogmcp_SubMenu(); ?></div>
    <div id="divMain2">
        <div id="aboutApp" class="zblogmcp-app">
            <el-card shadow="never" style="max-width:760px;">
                <template #header>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-circle-info" aria-hidden="true" style="color:#409eff;"></i>
                        <span style="font-size:16px; font-weight:bold;">关于</span>
                    </div>
                </template>

                <el-descriptions :column="1" border>
                    <el-descriptions-item label="开发者">
                        <a href="https://blog.waytomilky.com/" target="_blank" rel="noopener noreferrer">玄火</a>
                    </el-descriptions-item>
                    <el-descriptions-item label="其他作品">
                        <a href="https://app.zblogcn.com/?auth=8a4f3086-399a-4543-a0be-24c676a0a6b5" target="_blank" rel="noopener noreferrer">查看其他作品</a>
                    </el-descriptions-item>
                    <el-descriptions-item label="QQ">
                        610176732
                    </el-descriptions-item>
                    <el-descriptions-item label="QQ技术群">
                        335011903
                    </el-descriptions-item>
                    <el-descriptions-item label="赞助开发者">
                        <a href="https://blog.waytomilky.com/14.html" target="_blank" rel="noopener noreferrer">支持开发者</a>
                    </el-descriptions-item>
                    <el-descriptions-item label="开发者声明">
                        本插件由「玄火」捐助社区，助力 zblog 社区发展壮大！
                    </el-descriptions-item>
                </el-descriptions>
            </el-card>

            <el-card shadow="never" class="mcp-info-card" style="margin-top:15px;">
                <template #header>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-rocket" aria-hidden="true" style="color:#409eff;"></i>
                        <span style="font-size:16px; font-weight:bold;">快速开始</span>
                    </div>
                </template>
                <el-steps :active="3" finish-status="success" align-center>
                    <el-step title="创建 ApiKey" description="新增或选择一个已启用的 ApiKey，确认权限范围满足 Claw 使用场景。"></el-step>
                    <el-step title="复制配置" description="点击操作栏的“复制配置”，自动复制包含 MCP 地址和 Bearer Token 的 JSON。"></el-step>
                    <el-step title="粘贴到 Claw" description="打开 Claw 的 MCP 服务配置，将 JSON 粘贴保存，刷新或重启后即可调用博客工具。"></el-step>
                </el-steps>
                <el-alert
                    type="info"
                    :closable="false"
                    show-icon
                    style="margin-top:16px;"
                    title="复制配置会按当前站点域名和当前行 ApiKey 自动组装，可直接用于支持 HTTP MCP Server 的 Claw 工具。"
                ></el-alert>
                <el-alert
                    type="warning"
                    :closable="false"
                    show-icon
                    style="margin-top:10px;"
                    title="如果 MCP 配置入口比较难找，可以把复制的配置发给 Claw，并提示它“请帮我把这段 MCP 配置添加到当前工具的 MCP 服务配置中”。"
                ></el-alert>
            </el-card>
        </div>
    </div>
</div>

<script>
const { createApp } = Vue;

createApp({}).use(ElementPlus).mount('#aboutApp');
</script>
<?php require_once __DIR__ . '/foot.php'; ?>
