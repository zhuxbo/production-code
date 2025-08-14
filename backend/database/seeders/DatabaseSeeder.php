<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Setting;
use App\Models\SettingGroup;
use App\Models\UserLevel;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Admin::where('username', 'admin')->delete();
        Admin::create([
            'username' => 'admin',
            'password' => '123456',
        ]);

        UserLevel::where('custom', 0)->delete();
        $userLevels = [
            ['id' => 1, 'name' => '标准会员', 'code' => 'standard', 'custom' => 0, 'weight' => 1],
            ['id' => 2, 'name' => '金牌会员', 'code' => 'gold', 'custom' => 0, 'weight' => 2],
            ['id' => 3, 'name' => '铂金会员', 'code' => 'platinum', 'custom' => 0, 'weight' => 3],
            ['id' => 4, 'name' => '皇冠会员', 'code' => 'crown', 'custom' => 0, 'weight' => 4],
            ['id' => 5, 'name' => '合作伙伴', 'code' => 'partner', 'custom' => 0, 'weight' => 5],
        ];
        foreach ($userLevels as $level) {
            UserLevel::create($level);
        }

        SettingGroup::whereIn('id', [1, 2, 3, 4, 5, 6, 7])->delete();
        $settingGroups = [
            ['id' => 1, 'name' => 'site', 'title' => '站点设置', 'description' => null, 'weight' => 1],
            ['id' => 2, 'name' => 'ca', 'title' => '证书接口', 'description' => null, 'weight' => 2],
            ['id' => 3, 'name' => 'mail', 'title' => '邮件设置', 'description' => null, 'weight' => 3],
            ['id' => 4, 'name' => 'sms', 'title' => '短信设置', 'description' => null, 'weight' => 4],
            ['id' => 5, 'name' => 'alipay', 'title' => '支付宝设置', 'description' => null, 'weight' => 5],
            ['id' => 6, 'name' => 'wechat', 'title' => '微信支付设置', 'description' => null, 'weight' => 6],
            ['id' => 7, 'name' => 'bankAccount', 'title' => '银行账户设置', 'description' => null, 'weight' => 7],
        ];
        foreach ($settingGroups as $group) {
            SettingGroup::create($group);
        }

        Setting::whereIn('group_id', [1, 2, 3, 4, 5, 6, 7])->delete();
        $settings = [
            ['group_id' => 1, 'key' => 'url', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '用户URL', 'weight' => 1],
            ['group_id' => 1, 'key' => 'name', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '站点名称', 'weight' => 2],
            ['group_id' => 1, 'key' => 'callbackToken', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '回调令牌', 'weight' => 3],
            ['group_id' => 1, 'key' => 'adminEmail', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '管理员邮箱（用于接收系统错误通知）', 'weight' => 4],
            ['group_id' => 2, 'key' => 'sources', 'type' => 'array', 'options' => null, 'is_multiple' => 0, 'value' => ['default' => 'Default'], 'description' => '来源', 'weight' => 1],
            ['group_id' => 2, 'key' => 'url', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => 'Default接口URL', 'weight' => 2],
            ['group_id' => 2, 'key' => 'token', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => 'Default接口令牌', 'weight' => 3],
            ['group_id' => 3, 'key' => 'server', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => 'SMTP 服务器', 'weight' => 1],
            ['group_id' => 3, 'key' => 'port', 'type' => 'integer', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => 'SMTP 端口', 'weight' => 2],
            ['group_id' => 3, 'key' => 'user', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => 'SMTP 用户', 'weight' => 3],
            ['group_id' => 3, 'key' => 'password', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => 'SMTP 密码', 'weight' => 4],
            ['group_id' => 3, 'key' => 'senderMail', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '发件人邮箱', 'weight' => 5],
            ['group_id' => 3, 'key' => 'senderName', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '发件人名称', 'weight' => 6],
            ['group_id' => 3, 'key' => 'issueNotice', 'type' => 'base64', 'options' => null, 'is_multiple' => 0, 'value' => $this->getIssueNotice(), 'description' => '签发通知邮件模板', 'weight' => 7],
            ['group_id' => 3, 'key' => 'expireNotice', 'type' => 'base64', 'options' => null, 'is_multiple' => 0, 'value' => $this->getExpireNotice(), 'description' => '到期通知邮件模板', 'weight' => 8],
            ['group_id' => 4, 'key' => 'gateway', 'type' => 'select', 'options' => [['label' => '阿里云', 'value' => 'aliyun'], ['label' => '腾讯云', 'value' => 'tencent'], ['label' => '华为云', 'value' => 'huawei']], 'is_multiple' => 0, 'value' => null, 'description' => '网关', 'weight' => 0],
            ['group_id' => 4, 'key' => 'aliyun', 'type' => 'array', 'options' => null, 'is_multiple' => 0, 'value' => ['access_key_id' => null, 'access_key_secret' => null, 'sign_name' => null, 'register_template_id' => null, 'bind_template_id' => null, 'reset_template_id' => null], 'description' => '阿里云配置', 'weight' => 2],
            ['group_id' => 4, 'key' => 'tencent', 'type' => 'array', 'options' => null, 'is_multiple' => 0, 'value' => ['sdk_app_id' => null, 'secret_id' => null, 'secret_key' => null, 'sign_name' => null, 'register_template_id' => null, 'bind_template_id' => null, 'reset_template_id' => null], 'description' => '腾讯云配置', 'weight' => 3],
            ['group_id' => 4, 'key' => 'huawei', 'type' => 'array', 'options' => null, 'is_multiple' => 0, 'value' => ['endpoint' => null, 'app_key' => null, 'app_secret' => null, 'sender' => null, 'signature' => null, 'register_template_id' => null, 'bind_template_id' => null, 'reset_template_id' => null], 'description' => '华为云配置', 'weight' => 4],
            ['group_id' => 4, 'key' => 'expire', 'type' => 'integer', 'options' => null, 'is_multiple' => 0, 'value' => 600, 'description' => '验证码过期时间(秒)', 'weight' => 9],
            ['group_id' => 5, 'key' => 'app_id', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '应用ID', 'weight' => 0],
            ['group_id' => 5, 'key' => 'app_secret_cert', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '应用私钥', 'weight' => 0],
            ['group_id' => 5, 'key' => 'appCertPublicKey', 'type' => 'base64', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '应用公钥', 'weight' => 0],
            ['group_id' => 5, 'key' => 'certPublicKeyRSA2', 'type' => 'base64', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '支付宝公钥RSA2', 'weight' => 0],
            ['group_id' => 5, 'key' => 'rootCert', 'type' => 'base64', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '支付宝根证书', 'weight' => 0],
            ['group_id' => 6, 'key' => 'mch_id', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '商户号', 'weight' => 0],
            ['group_id' => 6, 'key' => 'mch_secret_key', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => 'v3 商户秘钥', 'weight' => 0],
            ['group_id' => 6, 'key' => 'apiclientKey', 'type' => 'base64', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '商户私钥', 'weight' => 0],
            ['group_id' => 6, 'key' => 'apiclientCert', 'type' => 'base64', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '商户公钥证书', 'weight' => 0],
            ['group_id' => 6, 'key' => 'publicKeyId', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '微信支付公钥ID', 'weight' => 0],
            ['group_id' => 6, 'key' => 'publicKey', 'type' => 'base64', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '微信支付公钥', 'weight' => 0],
            ['group_id' => 6, 'key' => 'mp_app_id', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '关联的 APP 公众号 小程序 的ID', 'weight' => 0],
            ['group_id' => 7, 'key' => 'name', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '户名', 'weight' => 0],
            ['group_id' => 7, 'key' => 'account', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '账号', 'weight' => 0],
            ['group_id' => 7, 'key' => 'bank', 'type' => 'string', 'options' => null, 'is_multiple' => 0, 'value' => null, 'description' => '开户行', 'weight' => 0],
        ];
        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }

    private function getIssueNotice(): string
    {
        return
<<<'EOT'
<center>
  <div
    style="
      max-width: 800px;
      margin: 0 auto;
      font-family: -apple-system, BlinkMacSystemFont, 'Microsoft YaHei', sans-serif;
      border: 1px solid #edecec;
      padding: 20px;
      background-color: #fff;
      text-align: left;
    "
  >
    <h3 style="color: #333">证书产品名称： {#product}</h3>

    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin: 20px 0">
      <p style="color: #dc3545; font-weight: bold">请保存好此邮件，证书重新安装需要用到!</p>
    </div>

    <h3 style="color: #333; margin-top: 30px">附件证书提供不同服务器的格式</h3>

    <div style="margin: 20px 0">
      <div style="margin: 15px 0">
        <b>iis/{#domain}.pfx</b>
        <span style="color: #666"> : 适用于 IIS （IIS6/7/8/10） Exchange Server</span>
      </div>

      <div style="margin: 15px 0">
        <b>apache/{#domain}.crt</b>
        <span style="color: #666"> : 适用于 Apache （cPanel 、 DirectAdmin）</span>
      </div>

      <div style="margin: 15px 0">
        <b>nginx/{#domain}.crt</b>
        <span style="color: #666"> : 适用于 Nginx （NodeJS 、阿里云负载均衡、LigHttpd、Golang、Ruby、Python）</span>
      </div>

      <div style="margin: 15px 0">
        <b>tomcat/{#domain}.jks</b>
        <span style="color: #666"> : Tomcat 、JBoss 、WebSphere 、WebLogic（Java平台的大多数都支持这个）</span>
      </div>
    </div>

    <h3 style="color: #333">证书安装教程</h3>
    <a href="{#site_url}/help" style="color: #007bff; text-decoration: none">
      如果点击不能打开链接，请复制此链接在浏览器中打开: {#site_url}/help
    </a>

    <div style="margin: 30px 0">
      <h2 style="color: #333">密钥(KEY) -- 宝塔面板/1Panel/CDN/虚拟主机等</h2>
      <code
        style="
          display: block;
          overflow-x: auto;
          background-color: #f8f9fa;
          border: 1px solid #e9ecef;
          border-radius: 4px;
          padding: 1rem;
          margin: 1rem 0;
          font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
          font-size: 13px;
          line-height: 1.45;
          color: #333;
          word-wrap: break-word;
          white-space: pre;
        "
        >{#key}</code
      >

      <h2 style="color: #333">证书(PEM格式) -- 宝塔面板/1Panel/CDN/虚拟主机等</h2>
      <code
        style="
          display: block;
          overflow-x: auto;
          background-color: #f8f9fa;
          border: 1px solid #e9ecef;
          border-radius: 4px;
          padding: 1rem;
          margin: 1rem 0;
          font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
          font-size: 13px;
          line-height: 1.45;
          color: #333;
          word-wrap: break-word;
          white-space: pre;
        "
        >{#pem}</code
      >

    </div>
  </div>
</center>
EOT;
    }

    private function getExpireNotice(): string
    {
        return
<<<'EOT'
<center>
  <div style="
    max-width: 800px;
    margin: 0 auto;
    color: #333;
    font-family: -apple-system, BlinkMacSystemFont, 'Microsoft YaHei', sans-serif;
    border: 1px solid #edecec;
    padding: 20px;
    background-color: #fff;
  ">
    <tbody>
      <tr>
        <td style="padding: 20px;">
          <div style="font-size: 16px; margin-top: 20px; text-align: left;">
            尊敬的用户 <span style="color: #3366cc;">{#username}</span> ，您好：
          </div>

          <div style="font-weight: bold; margin: 15px 0; text-align: left;">
            您的下列SSL证书已经到期，为了不影响您的网站正常访问，请及时续费！
          </div>

          <div style="font-weight: bold; margin: 15px 0; text-align: left;">
            <a href="{#site_url}" style="color: #007bff; text-decoration: none">
			  {#site_url}
			</a>
          </div>

          <table style="
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
          ">
            <thead>
              <tr style="background-color: #e4e4e4;">
                <th style="padding: 10px; border: 1px solid #ccc; width: 60px;">序号</th>
                <th style="padding: 10px; border: 1px solid #ccc;">域名</th>
                <th style="padding: 10px; border: 1px solid #ccc; width: 200px;">到期时间</th>
                <th style="padding: 10px; border: 1px solid #ccc; width: 80px;">剩余天数</th>
              </tr>
            </thead>
            <tbody>
              {#list}
            </tbody>
          </table>
        </td>
      </tr>
    </tbody>
  </table>
</center>
EOT;
    }
}
