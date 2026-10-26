<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const step = ref(0);
const status = ref('');
const showPassword = ref(false);
const showConfirmation = ref(false);
const page = usePage();
const form = useForm({
    database_driver: 'mysql', database_host: '127.0.0.1', database_port: '3306',
    database_name: '', database_username: 'root', database_password: '',
    admin_username: 'admin', admin_name: '', admin_phone: '', admin_email: '',
    admin_password: '', admin_password_confirmation: '',
});
const steps = ['Kết nối cơ sở dữ liệu', 'Tạo tài khoản quản trị', 'Xác nhận & hoàn tất'];
const errors = computed(() => page.props.errors ?? {});

function next() {
    status.value = '';
    if (step.value === 0) {
        const required = form.database_driver === 'sqlite' ? ['database_name'] : ['database_host', 'database_name', 'database_username'];
        const missing = required.find((field) => !String(form[field] ?? '').trim());
        if (missing) {
            form.setError(missing, 'Vui lòng điền trường bắt buộc.');
            return;
        }
    }
    if (step.value === 1) {
        const missing = ['admin_username', 'admin_name', 'admin_phone', 'admin_email', 'admin_password', 'admin_password_confirmation'].find((field) => !String(form[field] ?? '').trim());
        if (missing) {
            form.setError(missing, 'Vui lòng điền trường bắt buộc.');
            return;
        }
        if (form.admin_password.length < 8) {
            form.setError('admin_password', 'Mật khẩu cần tối thiểu 8 ký tự.');
            return;
        }
        if (form.admin_password !== form.admin_password_confirmation) {
            form.setError('admin_password_confirmation', 'Hai mật khẩu chưa trùng khớp.');
            return;
        }
    }
    form.clearErrors();
    if (step.value === 1) {
        status.value = '';
    }
    step.value = Math.min(step.value + 1, 2);
}
function back() { status.value = ''; step.value = Math.max(0, step.value - 1); }
function testConnection() {
    status.value = '';
    form.post('/setup/test', {
        preserveScroll: true,
        onSuccess: () => { status.value = page.props.flash?.status ?? ''; },
    });
}
function install() {
    form.post('/setup/install', {
        preserveScroll: true,
        onSuccess: () => window.location.replace('/login'),
    });
}
function changeDriver() {
    form.database_port = form.database_driver === 'pgsql' ? '5432' : form.database_driver === 'mysql' ? '3306' : '';
    form.clearErrors();
}
</script>

<template>
    <Head title="Thiết lập ban đầu" />
    <div class="setup-shell">
        <header class="topbar">
            <a class="brand" href="/setup"><span class="brand-mark">N</span><span>NỀN TẢNG <i>/ SETUP</i></span></a>
            <span class="secure">⌑&nbsp; Phiên thiết lập bảo mật</span>
        </header>
        <div class="layout">
            <aside class="rail">
                <div class="eyebrow">Chào mừng bạn</div>
                <h1>Hãy bắt đầu với thiết lập ban đầu</h1>
                <p>Chỉ mất vài phút để kết nối dữ liệu và chuẩn bị không gian làm việc.</p>
                <ol class="steps">
                    <li v-for="(label, index) in steps" :key="label" :class="{ active: step === index, done: step > index }">
                        <span class="stepnum">{{ step > index ? '✓' : index + 1 }}</span><span>{{ label }}</span>
                    </li>
                </ol>
                <div class="rail-foot"><strong>Thiết lập một lần, sẵn sàng sử dụng</strong>Cấu hình sẽ được lưu an toàn trên máy chủ của bạn.</div>
            </aside>
            <main class="main">
                <section v-if="step === 0" class="content">
                    <div class="kicker"><i></i> BƯỚC 01 · KẾT NỐI</div>
                    <h2>Kết nối cơ sở dữ liệu</h2>
                    <p class="lead">Website cần cơ sở dữ liệu để lưu tài khoản và dữ liệu vận hành. Máy chủ sẽ tạo database nếu chưa tồn tại.</p>
                    <div class="callout"><span>ⓘ</span><div><strong>Chưa có database? Không sao.</strong><p>Thông tin kết nối cần quyền tạo database mới. Trình cài đặt sẽ tạo bảng và cấu hình website sau khi kết nối thành công.</p></div></div>
                    <div class="grid">
                        <label>Loại cơ sở dữ liệu <b>*</b><select v-model="form.database_driver" @change="changeDriver"><option value="mysql">MySQL / MariaDB</option><option value="pgsql">PostgreSQL</option><option value="sqlite">SQLite</option></select></label>
                        <label v-if="form.database_driver !== 'sqlite'">Máy chủ (Host) <b>*</b><input v-model="form.database_host" placeholder="127.0.0.1"><small>{{ form.errors.database_host }}</small></label>
                        <label v-if="form.database_driver !== 'sqlite'">Cổng kết nối<input v-model="form.database_port" placeholder="3306"></label>
                        <label>Tên database <b>*</b><input v-model="form.database_name" :placeholder="form.database_driver === 'sqlite' ? 'qlbh' : 'ten_database'"><small v-if="form.errors.database_name">{{ form.errors.database_name }}</small></label>
                        <label v-if="form.database_driver !== 'sqlite'">Tên người dùng <b>*</b><input v-model="form.database_username" placeholder="ten_nguoi_dung"><small v-if="form.errors.database_username">{{ form.errors.database_username }}</small></label>
                        <label v-if="form.database_driver !== 'sqlite'">Mật khẩu database<input v-model="form.database_password" type="password" placeholder="Nhập mật khẩu"></label>
                    </div>
                    <p v-if="errors.database" class="error-banner">{{ errors.database }}</p>
                    <p v-if="status" class="success-banner">{{ status }}</p>
                    <footer class="actions"><span class="note">Thông tin đăng nhập không được hiển thị trở lại.</span><div><button class="outline" type="button" :disabled="form.processing" @click="testConnection">↻ Kiểm tra kết nối</button><button class="primary" type="button" @click="next">Tiếp tục&nbsp; →</button></div></footer>
                </section>
                <section v-else-if="step === 1" class="content">
                    <div class="kicker"><i></i> BƯỚC 02 · TÀI KHOẢN</div>
                    <h2>Tạo tài khoản quản trị</h2>
                    <p class="lead">Bạn có thể đăng nhập bằng tên đăng nhập, email hoặc số điện thoại.</p>
                    <div class="grid">
                        <label>Tên đăng nhập <b>*</b><input v-model="form.admin_username" placeholder="admin" autocomplete="username"><small v-if="form.errors.admin_username">{{ form.errors.admin_username }}</small></label>
                        <label>Họ và tên <b>*</b><input v-model="form.admin_name" placeholder="Nguyễn Văn An" autocomplete="name"><small v-if="form.errors.admin_name">{{ form.errors.admin_name }}</small></label>
                        <label>Số điện thoại <b>*</b><input v-model="form.admin_phone" type="tel" placeholder="0912 345 678" autocomplete="tel"><small v-if="form.errors.admin_phone">{{ form.errors.admin_phone }}</small></label>
                        <label>Email quản trị <b>*</b><input v-model="form.admin_email" type="email" placeholder="ban@congty.vn" autocomplete="email"><small v-if="form.errors.admin_email">{{ form.errors.admin_email }}</small></label>
                        <label>Mật khẩu <b>*</b><span class="password"><input v-model="form.admin_password" :type="showPassword ? 'text' : 'password'" placeholder="Ít nhất 8 ký tự" autocomplete="new-password"><button type="button" @click="showPassword = !showPassword">{{ showPassword ? 'Ẩn' : 'Hiện' }}</button></span><small v-if="form.errors.admin_password">{{ form.errors.admin_password }}</small></label>
                        <label>Nhập lại mật khẩu <b>*</b><span class="password"><input v-model="form.admin_password_confirmation" :type="showConfirmation ? 'text' : 'password'" placeholder="Nhập lại mật khẩu" autocomplete="new-password"><button type="button" @click="showConfirmation = !showConfirmation">{{ showConfirmation ? 'Ẩn' : 'Hiện' }}</button></span><small v-if="form.errors.admin_password_confirmation">{{ form.errors.admin_password_confirmation }}</small></label>
                    </div>
                    <p v-if="errors.database" class="error-banner">{{ errors.database }}</p>
                    <footer class="actions"><button class="outline" type="button" @click="back">← Quay lại</button><button class="primary" type="button" @click="next">Tiếp tục&nbsp; →</button></footer>
                </section>
                <section v-else class="content">
                    <div class="kicker"><i></i> BƯỚC 03 · RÀ SOÁT</div><h2>Kiểm tra lại thông tin</h2>
                    <p class="lead">Mọi thứ đã sẵn sàng. Xác nhận cấu hình trước khi khởi tạo website.</p>
                    <div class="summary"><div><h3>Cơ sở dữ liệu <button type="button" @click="step = 0">Chỉnh sửa</button></h3><p><span>Loại</span>{{ form.database_driver.toUpperCase() }}</p><p><span>Máy chủ</span>{{ form.database_driver === 'sqlite' ? 'SQLite trên máy chủ' : `${form.database_host}:${form.database_port}` }}</p><p><span>Database</span>{{ form.database_name }}</p><p><span>Người dùng</span>{{ form.database_driver === 'sqlite' ? '—' : form.database_username }}</p></div><div><h3>Tài khoản quản trị <button type="button" @click="step = 1">Chỉnh sửa</button></h3><p><span>Tên đăng nhập</span>{{ form.admin_username }}</p><p><span>Họ và tên</span>{{ form.admin_name }}</p><p><span>Số điện thoại</span>{{ form.admin_phone }}</p><p><span>Email</span>{{ form.admin_email }}</p></div></div>
                    <div class="security">◈&nbsp; Mật khẩu được mã hóa và không hiển thị trong phần rà soát. Database hiện có dữ liệu sẽ không bị ghi đè.</div>
                    <p v-if="errors.database" class="error-banner">{{ errors.database }}</p>
                    <footer class="actions"><button class="outline" type="button" @click="back">← Quay lại</button><button class="primary" type="button" :disabled="form.processing" @click="install">{{ form.processing ? 'Đang khởi tạo…' : 'Khởi tạo website →' }}</button></footer>
                </section>
            </main>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap');
:global(body){margin:0;background:#f6f7f5;color:#18231f;font-family:'DM Sans',sans-serif}.setup-shell{max-width:1180px;margin:auto;padding:24px 34px 52px}.topbar{height:48px;display:flex;align-items:center;justify-content:space-between;margin-bottom:30px}.brand{display:flex;align-items:center;gap:11px;color:#18231f;text-decoration:none;font:800 15px Manrope,sans-serif}.brand i{font-style:normal;color:#8e9a93;font-weight:500}.brand-mark{width:34px;height:34px;border-radius:11px;background:#157b59;display:grid;place-items:center;color:#fff}.secure{font-size:12px;color:#76827d}.layout{display:grid;grid-template-columns:290px minmax(0,1fr);min-height:640px;border:1px solid #e9ece8;border-radius:22px;overflow:hidden;background:#fff;box-shadow:0 22px 70px #1c332313}.rail{background:#f9faf8;border-right:1px solid #e6eae6;padding:42px 28px 28px;display:flex;flex-direction:column}.eyebrow,.kicker{font-size:10px;letter-spacing:1.3px;color:#84918b;font-weight:700}.rail h1{font:700 22px/1.35 Manrope,sans-serif;letter-spacing:-.6px;margin:14px 0 10px}.rail>p,.lead{font-size:13px;line-height:1.7;color:#76827d;margin:0}.steps{margin:38px 0 0;padding:0;list-style:none}.steps li{display:flex;gap:13px;position:relative;padding:0 0 25px;min-height:61px;color:#89948f;font-size:12px;font-weight:600}.steps li:not(:last-child):after{position:absolute;content:'';width:1px;background:#dde4de;left:13px;top:30px;bottom:0}.stepnum{flex:none;width:27px;height:27px;border-radius:50%;border:1px solid #dce3dd;background:#fff;display:grid;place-items:center;font-size:11px;z-index:1}.steps li.active{color:#18231f}.steps li.active .stepnum{background:#157b59;border-color:#157b59;color:#fff;box-shadow:0 0 0 4px #e9f4ef}.steps li.done{color:#157b59}.rail-foot{margin-top:auto;border-top:1px solid #e6eae6;padding-top:18px;font-size:11px;line-height:1.65;color:#85918a}.rail-foot strong{display:block;color:#4b5b53;margin-bottom:3px}.main{padding:43px clamp(28px,6vw,76px) 32px}.content{max-width:635px;margin:auto}.kicker{display:flex;align-items:center;gap:7px;color:#157b59}.kicker i{width:7px;height:7px;border-radius:50%;background:#42a67e}.content h2{font:700 clamp(24px,3vw,32px)/1.22 Manrope,sans-serif;letter-spacing:-1px;margin:12px 0 9px}.lead{margin-bottom:25px;max-width:560px}.callout{display:flex;gap:12px;padding:14px 15px;background:#f2f7f4;border:1px solid #e1eee6;border-radius:11px;margin-bottom:20px;color:#157b59}.callout strong{display:block;color:#18231f;font-size:12px;margin-bottom:3px}.callout p{color:#6c7b73;font-size:11px;line-height:1.55;margin:0}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.grid label{display:block;font-size:11px;font-weight:600;color:#43524a}.grid label b{color:#cf5c50}.grid input,.grid select{display:block;height:43px;width:100%;margin-top:7px;padding:0 12px;border:1px solid #dfe5e0;background:#fff;border-radius:8px;color:#26352d;font:12px 'DM Sans',sans-serif;outline:0}.grid input:focus,.grid select:focus{border-color:#63a98c;box-shadow:0 0 0 3px #e9f5ee}.grid small{display:block;color:#bc4a41;font-size:10px;margin-top:5px}.password{position:relative;display:block}.password input{padding-right:52px}.password button{position:absolute;right:9px;top:19px;background:none;border:0;color:#738078;font-size:10px;cursor:pointer}.actions{border-top:1px solid #e6eae6;padding-top:18px;margin-top:24px;display:flex;justify-content:space-between;align-items:center;gap:12px}.actions>div{display:flex;gap:9px}.actions button{border-radius:8px;padding:11px 15px;font:700 12px 'DM Sans',sans-serif;cursor:pointer}.primary{border:0;background:#157b59;color:white}.primary:hover{background:#0f6045}.primary:disabled{opacity:.6;cursor:wait}.outline{border:1px solid #dfe5e0;background:#fff;color:#385548}.note{font-size:10px;color:#8b9690}.error-banner,.success-banner{padding:10px 12px;border-radius:8px;font-size:11px;line-height:1.5}.error-banner{background:#fff2ef;color:#a2463a}.success-banner{background:#edf7f0;color:#247147}.summary{border:1px solid #e6eae6;border-radius:10px;overflow:hidden}.summary>div{padding:15px 16px;border-bottom:1px solid #e6eae6}.summary>div:last-child{border:0}.summary h3{display:flex;justify-content:space-between;margin:0 0 10px;font-size:11px}.summary h3 button{color:#157b59;border:0;background:none;font-size:10px;cursor:pointer}.summary p{display:grid;grid-template-columns:145px 1fr;gap:10px;font-size:11px;margin:7px 0}.summary p span{color:#87938c}.security{background:#faf7ef;border:1px solid #f0ead9;border-radius:9px;padding:12px 13px;margin-top:15px;color:#8a6b32;font-size:10px;line-height:1.55}@media(max-width:760px){.setup-shell{padding:15px 14px 30px}.topbar{margin-bottom:16px}.secure{font-size:0}.layout{grid-template-columns:1fr;min-height:0;border-radius:16px}.rail{padding:22px 22px 15px;border-right:0;border-bottom:1px solid #e6eae6}.rail h1{font-size:18px;margin:7px 0 5px}.rail>p{font-size:11px}.steps{display:flex;margin:20px 0 0;gap:0}.steps li{flex:1;padding:0;min-height:0;font-size:0;justify-content:center}.steps li:not(:last-child):after{height:1px;width:auto;left:50%;right:-50%;top:13px;bottom:auto}.stepnum{width:25px;height:25px;font-size:10px}.rail-foot{display:none}.main{padding:27px 19px 22px}.content h2{font-size:25px}.grid{gap:12px 10px}.actions{margin-top:18px}.note{display:none}}@media(max-width:440px){.grid{grid-template-columns:1fr}.summary p{grid-template-columns:110px 1fr}.actions button{padding:10px 12px}}
</style>
