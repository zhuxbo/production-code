<?php

namespace App\Http\Traits;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait LegacyPasswordAuth
{
    /**
     * 验证旧系统的密码格式 md5(md5($password) . $salt)
     */
    protected function verifyLegacyPassword(string $account, string $password): ?User
    {
        // 检查old_users表是否存在
        if (! Schema::hasTable('old_users')) {
            return null;
        }

        // 直接在old_users表中根据账号类型查找用户记录
        $oldUser = null;
        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            $oldUser = DB::table('old_users')->where('email', $account)->first();
        } elseif (preg_match('/^1[3-9]\d{9}$/', $account)) {
            $oldUser = DB::table('old_users')->where('mobile', $account)->first();
        } elseif (preg_match('/^[a-zA-Z][a-zA-Z0-9]{2,19}$/', $account)) {
            $oldUser = DB::table('old_users')->where('username', $account)->first();
        }

        if (! $oldUser || ! $oldUser->password || ! $oldUser->salt) {
            return null;
        }

        // 验证旧系统密码格式: md5(md5($password) . $salt)
        $expectedHash = md5(md5($password).$oldUser->salt);

        if ($expectedHash === $oldUser->password) {
            // 密码验证成功，查找对应的新系统用户
            $user = User::find($oldUser->id);
            if (! $user) {
                return null;
            }

            // 将密码更新到新系统
            $user->password = $password; // 这会触发User模型的setPasswordAttribute自动hash
            $user->save();

            // 删除旧用户表中的记录，避免重复验证和数据冗余
            DB::table('old_users')->where('id', $oldUser->id)->delete();

            return $user;
        }

        return null;
    }
}
