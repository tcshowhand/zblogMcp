<?php
/**
 * zblogMcp - ApiKey add/edit page.
 */
require_once __DIR__ . '/header.php';

$is_edit = false;
$key_data = [
    'id' => 0,
    'name' => '',
    'api_key' => '',
    'status' => 1,
    'scope_read' => 1,
    'scope_write' => 1,
    'scope_category' => 1,
    'scope_tag' => 1,
    'scope_media' => 1,
    'scope_comment' => 1,
    'remark' => '',
];

if (GetVars('id', 'GET')) {
    $is_edit = true;
    $row = zblogmcp_get_key(GetVars('id', 'GET'));
    if ($row) {
        $key_data = array_merge($key_data, $row);
    } else {
        zblogmcp_error('ApiKey record not found');
    }
}

if (GetVars('act', 'GET') == 'save') {
    CheckIsRefererValid();
    if ($is_edit || GetVars('id', 'POST')) {
        zblogmcp_update_key($_POST);
    } else {
        zblogmcp_add_key($_POST);
    }
}

$key_json = json_encode($key_data, JSON_UNESCAPED_UNICODE);
$csrf_token = $zbp->GetCSRFToken();
?>
<div id="divMain" class="plugin-area">
    <div class="divHeader"><?php echo $zblogmcp_plugin_info['name']; ?></div>
    <div class="SubMenu"><?php echo zblogmcp_SubMenu(); ?></div>
    <div id="divMain2">
        <div id="editApp" class="zblogmcp-app">
            <el-card shadow="never" style="max-width:760px;">
                <template #header>
                    <span style="font-size:16px; font-weight:bold;">{{ isEdit ? '编辑 ApiKey' : '新增 ApiKey' }}</span>
                </template>

                <el-form :model="form" label-width="110px" size="large">
                    <el-form-item label="名称" required>
                        <el-input v-model="form.name" placeholder="例如：生产环境 Key、测试 Key" maxlength="100"></el-input>
                    </el-form-item>

                    <el-form-item label="ApiKey" required>
                        <div style="display:flex; gap:8px; width:100%;">
                            <el-input
                                v-model="form.api_key"
                                :type="showKey ? 'text' : 'password'"
                                placeholder="输入或生成 ApiKey"
                                style="flex:1;"
                            ></el-input>
                            <el-button @click="showKey = !showKey">
                                <i class="fa-solid" :class="showKey ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                                <span>{{ showKey ? '隐藏' : '显示' }}</span>
                            </el-button>
                            <el-button type="primary" plain @click="generateKey">
                                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span>随机生成</span>
                            </el-button>
                        </div>
                    </el-form-item>

                    <el-form-item label="状态">
                        <el-radio-group v-model="form.status">
                            <el-radio :label="1">启用</el-radio>
                            <el-radio :label="0">禁用</el-radio>
                        </el-radio-group>
                    </el-form-item>

                    <el-form-item label="权限范围">
                        <el-checkbox v-model="form.scope_read" :true-label="1" :false-label="0">读取文章</el-checkbox>
                        <el-checkbox v-model="form.scope_write" :true-label="1" :false-label="0">写入文章</el-checkbox>
                        <el-checkbox v-model="form.scope_category" :true-label="1" :false-label="0">管理分类</el-checkbox>
                        <el-checkbox v-model="form.scope_tag" :true-label="1" :false-label="0">标签管理</el-checkbox>
                        <el-checkbox v-model="form.scope_media" :true-label="1" :false-label="0">媒体管理</el-checkbox>
                        <el-checkbox v-model="form.scope_comment" :true-label="1" :false-label="0">评论管理</el-checkbox>
                    </el-form-item>

                    <el-form-item label="备注">
                        <el-input v-model="form.remark" type="textarea" :rows="3" placeholder="可选备注信息" maxlength="500"></el-input>
                    </el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="submitForm" :loading="submitting">
                            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i><span>保存</span>
                        </el-button>
                        <el-button @click="goBack">
                            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>返回列表</span>
                        </el-button>
                    </el-form-item>
                </el-form>
            </el-card>
        </div>
    </div>
</div>

<form id="hiddenForm" method="post" action="key_edit.php?act=save" style="display:none;">
    <input type="hidden" name="id" value="">
    <input type="hidden" name="name" value="">
    <input type="hidden" name="api_key" value="">
    <input type="hidden" name="status" value="">
    <input type="hidden" name="scope_read" value="">
    <input type="hidden" name="scope_write" value="">
    <input type="hidden" name="scope_category" value="">
    <input type="hidden" name="scope_tag" value="">
    <input type="hidden" name="scope_media" value="">
    <input type="hidden" name="scope_comment" value="">
    <input type="hidden" name="remark" value="">
    <input type="hidden" name="csrfToken" value="<?php echo $csrf_token; ?>">
</form>

<script>
const { createApp, ref, reactive } = Vue;

createApp({
    setup() {
        const rawData = <?php echo $key_json; ?>;
        const isEdit = rawData.id > 0;
        const form = reactive({
            id: rawData.id || 0,
            name: rawData.name || '',
            api_key: rawData.api_key || '',
            status: parseInt(rawData.status ?? 1),
            scope_read: parseInt(rawData.scope_read ?? 1),
            scope_write: parseInt(rawData.scope_write ?? 1),
            scope_category: parseInt(rawData.scope_category ?? 1),
            scope_tag: parseInt(rawData.scope_tag ?? 1),
            scope_media: parseInt(rawData.scope_media ?? 1),
            scope_comment: parseInt(rawData.scope_comment ?? 1),
            remark: rawData.remark || ''
        });
        const showKey = ref(!isEdit);
        const submitting = ref(false);

        const createRandomKey = () => {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let result = 'sk-mcp-';
            for (let i = 0; i < 40; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        };

        if (!isEdit && !form.api_key) {
            form.api_key = createRandomKey();
        }

        const generateKey = () => {
            const result = createRandomKey();
            form.api_key = result;
            showKey.value = true;
            ElementPlus.ElMessage.success('已生成随机 Key');
        };

        const submitForm = () => {
            if (!form.name.trim()) {
                ElementPlus.ElMessage.warning('请填写名称');
                return;
            }
            if (!form.api_key.trim()) {
                ElementPlus.ElMessage.warning('请填写 ApiKey');
                return;
            }
            submitting.value = true;
            const hiddenForm = document.getElementById('hiddenForm');
            hiddenForm.querySelector('[name=id]').value = form.id;
            hiddenForm.querySelector('[name=name]').value = form.name;
            hiddenForm.querySelector('[name=api_key]').value = form.api_key;
            hiddenForm.querySelector('[name=status]').value = form.status;
            hiddenForm.querySelector('[name=scope_read]').value = form.scope_read;
            hiddenForm.querySelector('[name=scope_write]').value = form.scope_write;
            hiddenForm.querySelector('[name=scope_category]').value = form.scope_category;
            hiddenForm.querySelector('[name=scope_tag]').value = form.scope_tag;
            hiddenForm.querySelector('[name=scope_media]').value = form.scope_media;
            hiddenForm.querySelector('[name=scope_comment]').value = form.scope_comment;
            hiddenForm.querySelector('[name=remark]').value = form.remark;
            hiddenForm.submit();
        };

        const goBack = () => { location.href = 'main.php'; };

        return { isEdit, form, showKey, submitting, generateKey, submitForm, goBack };
    }
}).use(ElementPlus).mount('#editApp');
</script>
<?php require_once __DIR__ . '/foot.php'; ?>
