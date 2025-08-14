<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as CommandAlias;

class DatabaseStructureFixCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:sync-structure {--dry-run : 仅检查不执行修复} {--force : 强制执行所有修复} {--temp-db-name= : 指定临时数据库名称}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步数据库结构与迁移文件，添加缺失的表和字段，调整索引（支持临时MySQL配置）';

    /**
     * 临时数据库配置
     */
    private string $tempDbName;

    private string $originalDbName;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🔍 开始同步数据库结构与迁移文件...');
        $this->newLine();

        try {
            // 1. 准备临时数据库
            $this->setupTemporaryDatabase();

            // 2. 在临时数据库运行迁移
            $this->runMigrationsOnTempDatabase();

            // 3. 比较数据库结构
            $differences = $this->compareStructures();

            if (empty($differences)) {
                $this->info('✅ 数据库结构已同步，无需修复。');
                $this->cleanupTemporaryDatabase();

                return CommandAlias::SUCCESS;
            }

            // 4. 显示差异
            $this->displayDifferences($differences);

            if ($dryRun) {
                $this->info('🔍 仅检查模式，不执行修复操作。');
                $this->cleanupTemporaryDatabase();

                return CommandAlias::SUCCESS;
            }

            // 5. 询问是否执行修复
            if (! $force && ! $this->confirm('是否执行上述同步操作？')) {
                $this->info('❌ 用户取消同步操作。');
                $this->cleanupTemporaryDatabase();

                return CommandAlias::SUCCESS;
            }

            // 6. 执行修复
            $this->executeFixes($differences);

            // 7. 清理临时数据库
            $this->cleanupTemporaryDatabase();

            return CommandAlias::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ 同步操作发生错误: '.$e->getMessage());
            $this->cleanupTemporaryDatabase();

            return CommandAlias::FAILURE;
        }
    }

    /**
     * 设置临时数据库
     *
     * @throws Exception
     */
    private function setupTemporaryDatabase(): void
    {
        $this->originalDbName = Config::get('database.connections.mysql.database');

        // 支持用户指定临时数据库名称
        $customTempDb = $this->option('temp-db-name');
        if ($customTempDb) {
            $this->tempDbName = $customTempDb;
            $this->info("📝 使用指定的临时数据库: $this->tempDbName");
        } else {
            $this->tempDbName = $this->originalDbName.'_temp_'.time();
            $this->info("📝 创建临时数据库: $this->tempDbName");
        }

        try {
            // 首先尝试使用默认MySQL配置创建临时数据库
            $this->createTemporaryDatabase();
            $this->setupTemporaryConnections();
        } catch (Exception $e) {
            // 检查是否是权限问题
            if (str_contains($e->getMessage(), 'Access denied') ||
                str_contains($e->getMessage(), 'denied') ||
                str_contains($e->getMessage(), 'privilege') ||
                str_contains($e->getMessage(), 'permission')) {
                $this->warn('⚠️  使用默认MySQL配置创建临时数据库失败，尝试使用临时MySQL配置...');

                // 尝试使用临时MySQL配置
                if ($this->tryTempMysqlConfig()) {
                    // 检查是否设置了临时数据库，如果设置了就使用它而不是创建新的
                    $tempDbFromEnv = env('DB_TEMP_DATABASE');
                    if ($tempDbFromEnv) {
                        $this->tempDbName = $tempDbFromEnv;
                        $this->info("📝 使用环境变量指定的临时数据库: $this->tempDbName");
                        $this->setupTemporaryConnections();
                    } else {
                        $this->createTemporaryDatabase();
                        $this->setupTemporaryConnections();
                    }
                } else {
                    $this->showPermissionErrorAndExit();
                }
            } else {
                // 其他错误直接抛出
                throw $e;
            }
        }
    }

    /**
     * 创建临时数据库
     */
    private function createTemporaryDatabase(): void
    {
        DB::statement("CREATE DATABASE IF NOT EXISTS `$this->tempDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    /**
     * 设置临时数据库连接
     */
    private function setupTemporaryConnections(): void
    {
        // 配置临时数据库连接
        Config::set('database.connections.temp', array_merge(
            Config::get('database.connections.mysql'),
            ['database' => $this->tempDbName]
        ));

        // 配置临时日志数据库连接（使用同一个临时数据库）
        Config::set('database.connections.temp_log', array_merge(
            Config::get('database.connections.log'),
            ['database' => $this->tempDbName]
        ));
    }

    /**
     * 尝试使用临时MySQL配置
     *
     * @noinspection LaravelFunctionsInspection
     */
    private function tryTempMysqlConfig(): bool
    {
        // 从环境变量读取临时MySQL配置
        $tempHost = env('DB_TEMP_HOST');
        $tempPort = env('DB_TEMP_PORT');
        $tempDatabase = env('DB_TEMP_DATABASE');
        $tempUsername = env('DB_TEMP_USERNAME');
        $tempPassword = env('DB_TEMP_PASSWORD');

        if (! $tempHost || ! $tempUsername || ! $tempPassword) {
            return false;
        }

        $this->info("📝 使用临时MySQL配置: $tempUsername@$tempHost");

        // 更新数据库配置
        Config::set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => $tempHost,
            'port' => $tempPort ?: '3306',
            'database' => $tempDatabase ?: $this->originalDbName,
            'username' => $tempUsername,
            'password' => $tempPassword,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_520_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]);

        // 更新日志数据库配置
        Config::set('database.connections.log', [
            'driver' => 'mysql',
            'host' => $tempHost,
            'port' => $tempPort ?: '3306',
            'database' => $tempDatabase ?: $this->originalDbName,
            'username' => $tempUsername,
            'password' => $tempPassword,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_520_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]);

        // 重新连接数据库
        DB::purge('mysql');
        DB::purge('log');

        return true;
    }

    /**
     * 显示权限错误并退出
     *
     * @throws Exception
     */
    private function showPermissionErrorAndExit(): void
    {
        $this->error('❌ 数据库权限不足，无法创建临时数据库！');
        $this->newLine();
        $this->warn('解决方案：');
        $this->line('1. 方案一：为数据库用户授予创建数据库权限');
        $this->line('   GRANT CREATE ON *.* TO '.$this->getDatabaseUser().'@\'%\';');
        $this->line('   FLUSH PRIVILEGES;');
        $this->newLine();
        $this->line('2. 方案二：使用具有足够权限的数据库账号');
        $this->line('   修改 .env 文件中的 DB_USERNAME 和 DB_PASSWORD');
        $this->newLine();
        $this->line('3. 方案三：预先创建临时数据库并使用 --temp-db-name 参数');
        $this->line('   CREATE DATABASE `temp_sync_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
        $this->line('   php artisan db:sync-structure --temp-db-name=temp_sync_db');
        $this->newLine();
        $this->line('4. 方案四：在 .env 文件中添加临时MySQL配置');
        $this->line('   DB_TEMP_HOST=localhost');
        $this->line('   DB_TEMP_PORT=3306');
        $this->line('   DB_TEMP_DATABASE=existing_database  # 使用已存在的数据库');
        $this->line('   DB_TEMP_USERNAME=root');
        $this->line('   DB_TEMP_PASSWORD=your_password');
        $this->line('   注意：当设置 DB_TEMP_DATABASE 时，将使用现有数据库，不会创建新的临时数据库');
        $this->newLine();
        $this->info('💡 提示：通常 root 或具有 DBA 权限的账号可以创建数据库');

        throw new Exception('数据库权限不足，无法创建临时数据库');
    }

    /**
     * 获取当前数据库用户名
     */
    private function getDatabaseUser(): string
    {
        return Config::get('database.connections.mysql.username', 'unknown');
    }

    /**
     * 在临时数据库运行迁移
     */
    private function runMigrationsOnTempDatabase(): void
    {
        $this->info('🔄 在临时数据库运行迁移...');

        // 临时切换数据库连接
        $originalConnection = Config::get('database.default');
        $originalLogConfig = Config::get('database.connections.log');

        Config::set('database.default', 'temp');
        // 临时将log连接指向临时数据库，确保日志表的迁移也在临时数据库中执行
        Config::set('database.connections.log', Config::get('database.connections.temp_log'));

        try {
            // 运行迁移
            Artisan::call('migrate:fresh', [
                '--force' => true,
                '--quiet' => true,
            ]);

            $this->info('✅ 迁移执行完成');
        } finally {
            // 恢复原始连接
            Config::set('database.default', $originalConnection);
            Config::set('database.connections.log', $originalLogConfig);
        }
    }

    /**
     * 比较数据库结构
     */
    private function compareStructures(): array
    {
        $this->info('🔍 比较数据库结构差异...');

        $differences = [
            'missing_tables' => [],
            'missing_columns' => [],
            'different_columns' => [],
            'index_differences' => [],
            'log_missing_tables' => [],
            'log_missing_columns' => [],
            'log_different_columns' => [],
            'log_index_differences' => [],
        ];

        // 比较主数据库表结构
        $this->compareConnectionTables('mysql', 'temp', $differences, '');

        // 比较日志数据库表结构（仅当与主数据库不同时）
        if (! $this->isDatabaseSame()) {
            $this->compareConnectionTables('log', 'temp_log', $differences, 'log_');
        } else {
            $this->info('📝 主数据库和日志数据库使用相同配置，跳过日志数据库比较');
        }

        return $differences;
    }

    /**
     * 比较指定连接的表结构
     */
    private function compareConnectionTables(string $currentConn, string $expectedConn, array &$differences, string $prefix): void
    {
        // 获取两个数据库的表结构
        $currentTables = $this->getDatabaseTables($currentConn);
        $expectedTables = $this->getDatabaseTables($expectedConn);

        $connType = $prefix ? '日志' : '主';

        // 检查缺失的表
        foreach ($expectedTables as $tableName => $expectedTable) {
            if (! isset($currentTables[$tableName])) {
                $differences[$prefix.'missing_tables'][] = $tableName;
                $this->warn("⚠️  {$connType}数据库表 '$tableName' 不存在");

                continue;
            }

            // 检查缺失的字段
            $currentColumns = $currentTables[$tableName]['columns'];
            $expectedColumns = $expectedTable['columns'];

            foreach ($expectedColumns as $columnName => $expectedColumn) {
                if (! isset($currentColumns[$columnName])) {
                    $differences[$prefix.'missing_columns'][$tableName][] = [
                        'name' => $columnName,
                        'definition' => $expectedColumn,
                    ];
                    $this->warn("⚠️  {$connType}数据库表 '$tableName' 缺失字段 '$columnName'");
                } else {
                    // 检查字段类型是否匹配
                    $currentColumn = $currentColumns[$columnName];
                    if ($this->columnDefinitionDiffers($currentColumn, $expectedColumn)) {
                        $differences[$prefix.'different_columns'][$tableName][] = [
                            'name' => $columnName,
                            'current' => $currentColumn,
                            'expected' => $expectedColumn,
                        ];
                        $this->warn("⚠️  {$connType}数据库表 '$tableName' 字段 '$columnName' 类型需要调整");
                    }
                }
            }

            // 检查索引差异
            $indexDiff = $this->compareIndexes(
                $currentTables[$tableName]['indexes'],
                $expectedTable['indexes']
            );

            if (! empty($indexDiff)) {
                $differences[$prefix.'index_differences'][$tableName] = $indexDiff;
                $this->warn("⚠️  {$connType}数据库表 '$tableName' 索引需要调整");
            }
        }
    }

    /**
     * 获取数据库表结构信息
     */
    private function getDatabaseTables(string $connection): array
    {
        $tables = [];
        $tableNames = DB::connection($connection)
            ->select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?',
                [Config::get("database.connections.$connection.database")]);

        foreach ($tableNames as $tableInfo) {
            $tableName = $tableInfo->TABLE_NAME;

            // 跳过迁移表
            if (in_array($tableName, ['migrations', 'failed_jobs', 'password_reset_tokens', 'personal_access_tokens'])) {
                continue;
            }

            $tables[$tableName] = [
                'columns' => $this->getTableColumns($connection, $tableName),
                'indexes' => $this->getTableIndexes($connection, $tableName),
            ];
        }

        return $tables;
    }

    /**
     * 获取表字段信息
     */
    private function getTableColumns(string $connection, string $tableName): array
    {
        $columns = [];
        $columnInfo = DB::connection($connection)
            ->select('SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
                [Config::get("database.connections.$connection.database"), $tableName]);

        foreach ($columnInfo as $column) {
            $columns[$column->COLUMN_NAME] = [
                'type' => $column->COLUMN_TYPE,
                'nullable' => $column->IS_NULLABLE === 'YES',
                'default' => $column->COLUMN_DEFAULT,
                'comment' => $column->COLUMN_COMMENT,
                'extra' => $column->EXTRA,
            ];
        }

        return $columns;
    }

    /**
     * 获取表索引信息
     */
    private function getTableIndexes(string $connection, string $tableName): array
    {
        $indexes = [];
        $indexInfo = DB::connection($connection)
            ->select('SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX',
                [Config::get("database.connections.$connection.database"), $tableName]);

        foreach ($indexInfo as $index) {
            $indexName = $index->INDEX_NAME;
            if (! isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'columns' => [],
                    'unique' => $index->NON_UNIQUE == 0,
                    'type' => $index->INDEX_TYPE,
                ];
            }
            $indexes[$indexName]['columns'][] = $index->COLUMN_NAME;
        }

        return $indexes;
    }

    /**
     * 检查字段定义是否不同
     */
    private function columnDefinitionDiffers(array $current, array $expected): bool
    {
        // 比较字段类型（忽略大小写）
        if (strtolower($current['type']) !== strtolower($expected['type'])) {
            return true;
        }

        // 比较可空性
        if ($current['nullable'] !== $expected['nullable']) {
            return true;
        }

        // 比较默认值（处理NULL值的情况）
        $currentDefault = $current['default'];
        $expectedDefault = $expected['default'];

        // 将空字符串和NULL视为等价（某些MySQL版本的差异）
        if (($currentDefault === '' && $expectedDefault === null) ||
            ($currentDefault === null && $expectedDefault === '')) {
            return false;
        } elseif ($currentDefault !== $expectedDefault) {
            return true;
        }

        // 比较额外属性（如auto_increment）
        if (strtolower($current['extra'] ?? '') !== strtolower($expected['extra'] ?? '')) {
            return true;
        }

        return false;
    }

    /**
     * 比较索引差异
     */
    private function compareIndexes(array $currentIndexes, array $expectedIndexes): array
    {
        $differences = [
            'missing' => [],
            'extra' => [],
            'different' => [],
        ];

        // 检查缺失的索引
        foreach ($expectedIndexes as $indexName => $expectedIndex) {
            if (! isset($currentIndexes[$indexName])) {
                $differences['missing'][$indexName] = $expectedIndex;
            } elseif ($this->indexDiffers($currentIndexes[$indexName], $expectedIndex)) {
                $differences['different'][$indexName] = [
                    'current' => $currentIndexes[$indexName],
                    'expected' => $expectedIndex,
                ];
            }
        }

        // 检查多余的索引（非主键）
        foreach ($currentIndexes as $indexName => $currentIndex) {
            if ($indexName !== 'PRIMARY' && ! isset($expectedIndexes[$indexName])) {
                $differences['extra'][$indexName] = $currentIndex;
            }
        }

        return array_filter($differences);
    }

    /**
     * 检查索引是否不同
     */
    private function indexDiffers(array $current, array $expected): bool
    {
        return $current['columns'] !== $expected['columns'] ||
            $current['unique'] !== $expected['unique'];
    }

    /**
     * 检查两个数据库连接是否使用相同的数据库
     */
    private function isDatabaseSame(): bool
    {
        $db1Host = Config::get('database.connections.mysql.host');
        $db1Port = Config::get('database.connections.mysql.port');
        $db1Database = Config::get('database.connections.mysql.database');

        $db2Host = Config::get('database.connections.log.host');
        $db2Port = Config::get('database.connections.log.port');
        $db2Database = Config::get('database.connections.log.database');

        return $db1Host === $db2Host &&
            $db1Port === $db2Port &&
            $db1Database === $db2Database;
    }

    /**
     * 清理临时数据库
     */
    private function cleanupTemporaryDatabase(): void
    {
        if (isset($this->tempDbName)) {
            // 只清理自动创建的临时数据库，不清理用户指定的数据库
            $customTempDb = $this->option('temp-db-name');
            $tempDbFromEnv = env('DB_TEMP_DATABASE');

            if (! $customTempDb && ! $tempDbFromEnv) {
                try {
                    DB::statement("DROP DATABASE IF EXISTS `$this->tempDbName`");
                    $this->info("🗑️  已清理临时数据库: $this->tempDbName");
                } catch (Exception $e) {
                    $this->warn('⚠️  清理临时数据库失败: '.$e->getMessage());
                }
            } else {
                $source = $customTempDb ? '命令行参数' : '环境变量';
                $this->info("📝 保留用户指定的临时数据库($source): $this->tempDbName");
            }
        }
    }

    /**
     * 显示差异
     */
    private function displayDifferences(array $differences): void
    {
        $this->newLine();
        $this->info('🔍 检测到以下需要同步的差异:');
        $this->info('==========================================');

        $count = 0;

        // 显示主数据库差异
        $count += $this->displayConnectionDifferences($differences, '', '主数据库');

        // 显示日志数据库差异
        $count += $this->displayConnectionDifferences($differences, 'log_', '日志数据库');

        $this->info("总计发现 $count 个差异需要同步。");
    }

    /**
     * 显示指定连接的差异
     */
    private function displayConnectionDifferences(array $differences, string $prefix, string $dbName): int
    {
        $count = 0;
        $hasContent = false;

        if (! empty($differences[$prefix.'missing_tables']) ||
            ! empty($differences[$prefix.'missing_columns']) ||
            ! empty($differences[$prefix.'different_columns']) ||
            ! empty($differences[$prefix.'index_differences'])) {
            $this->info("📊 $dbName:");
            $hasContent = true;
        }

        if (! empty($differences[$prefix.'missing_tables'])) {
            $this->info('   📋 缺失的表:');
            foreach ($differences[$prefix.'missing_tables'] as $table) {
                $this->line("      • $table");
                $count++;
            }
        }

        if (! empty($differences[$prefix.'missing_columns'])) {
            $this->info('   🔧 缺失的字段:');
            foreach ($differences[$prefix.'missing_columns'] as $table => $columns) {
                foreach ($columns as $column) {
                    $this->line("      • $table.{$column['name']}");
                    $count++;
                }
            }
        }

        if (! empty($differences[$prefix.'different_columns'])) {
            $this->info('   🔄 需要修改的字段:');
            foreach ($differences[$prefix.'different_columns'] as $table => $columns) {
                foreach ($columns as $column) {
                    $currentType = $column['current']['type'];
                    $expectedType = $column['expected']['type'];
                    $this->line("      • $table.{$column['name']} ($currentType → $expectedType)");
                    $count++;
                }
            }
        }

        if (! empty($differences[$prefix.'index_differences'])) {
            $this->info('   🔍 需要调整的索引:');
            foreach ($differences[$prefix.'index_differences'] as $table => $indexDiff) {
                foreach (['missing', 'different', 'extra'] as $type) {
                    if (! empty($indexDiff[$type])) {
                        foreach ($indexDiff[$type] as $indexName => $indexInfo) {
                            $action = match ($type) {
                                'missing' => '添加',
                                'different' => '修改',
                                'extra' => '删除',
                            };
                            $this->line("      • $table.$indexName ($action)");
                            $count++;
                        }
                    }
                }
            }
        }

        if ($hasContent) {
            $this->newLine();
        }

        return $count;
    }

    /**
     * 执行修复操作
     */
    private function executeFixes(array $differences): void
    {
        $this->info('🔧 开始执行同步操作...');
        $this->newLine();

        $successCount = 0;
        $failureCount = 0;

        try {
            // 同步主数据库
            $result = $this->executeConnectionFixes($differences, 'mysql', 'temp', '');
            $successCount += $result['success'];
            $failureCount += $result['failure'];

            // 同步日志数据库（仅当与主数据库不同时）
            if (! $this->isDatabaseSame()) {
                $result = $this->executeConnectionFixes($differences, 'log', 'temp_log', 'log_');
                $successCount += $result['success'];
                $failureCount += $result['failure'];
            }

            $this->newLine();
            if ($failureCount > 0) {
                $this->warn("⚠️  同步完成：$successCount 个操作成功，$failureCount 个操作失败。");
            } else {
                $this->info("✅ 所有 $successCount 个同步操作执行完成！");
            }
        } catch (Exception $e) {
            $this->error('❌ 同步操作发生严重错误: '.$e->getMessage());
        }
    }

    /**
     * 执行指定连接的修复操作
     */
    private function executeConnectionFixes(array $differences, string $connection, string $tempConnection, string $prefix): array
    {
        $result = ['success' => 0, 'failure' => 0];
        $connType = $prefix ? '日志' : '主';

        // 1. 创建缺失的表
        if (! empty($differences[$prefix.'missing_tables'])) {
            $this->info("🔧 同步{$connType}数据库缺失的表...");
            $tableResult = $this->createMissingTables($differences[$prefix.'missing_tables'], $connection, $tempConnection);
            $result['success'] += $tableResult['success'];
            $result['failure'] += $tableResult['failure'];
        }

        // 2. 添加缺失的字段
        if (! empty($differences[$prefix.'missing_columns'])) {
            $this->info("🔧 同步{$connType}数据库缺失的字段...");
            $columnResult = $this->addMissingColumns($differences[$prefix.'missing_columns'], $connection);
            $result['success'] += $columnResult['success'];
            $result['failure'] += $columnResult['failure'];
        }

        // 3. 修改字段类型
        if (! empty($differences[$prefix.'different_columns'])) {
            $this->info("🔧 同步{$connType}数据库字段类型...");
            $columnResult = $this->modifyDifferentColumns($differences[$prefix.'different_columns'], $connection);
            $result['success'] += $columnResult['success'];
            $result['failure'] += $columnResult['failure'];
        }

        // 4. 调整索引
        if (! empty($differences[$prefix.'index_differences'])) {
            $this->info("🔧 同步{$connType}数据库索引...");
            $indexResult = $this->fixIndexes($differences[$prefix.'index_differences'], $connection);
            $result['success'] += $indexResult['success'];
            $result['failure'] += $indexResult['failure'];
        }

        return $result;
    }

    /**
     * 创建缺失的表
     */
    private function createMissingTables(array $tables, string $connection = 'mysql', string $tempConnection = 'temp'): array
    {
        $result = ['success' => 0, 'failure' => 0];

        foreach ($tables as $tableName) {
            try {
                // 从临时数据库获取建表语句
                $createSql = $this->getCreateTableStatement($tableName, $tempConnection);

                // 在目标数据库执行
                DB::connection($connection)->statement($createSql);

                $this->info("✅ 创建表 $tableName 成功");
                $result['success']++;
            } catch (Exception $e) {
                $this->error("❌ 创建表 $tableName 失败: ".$e->getMessage());
                $result['failure']++;
            }
        }

        return $result;
    }

    /**
     * 获取建表语句
     */
    private function getCreateTableStatement(string $tableName, string $connection = 'temp'): string
    {
        $result = DB::connection($connection)->select("SHOW CREATE TABLE `$tableName`");

        return $result[0]->{'Create Table'};
    }

    /**
     * 添加缺失的字段
     */
    private function addMissingColumns(array $missingColumns, string $connection = 'mysql'): array
    {
        $result = ['success' => 0, 'failure' => 0];

        foreach ($missingColumns as $tableName => $columns) {
            foreach ($columns as $columnInfo) {
                try {
                    $columnDef = $this->buildColumnDefinition($columnInfo['definition']);
                    $sql = "ALTER TABLE `$tableName` ADD COLUMN `{$columnInfo['name']}` $columnDef";

                    DB::connection($connection)->statement($sql);

                    $this->info("✅ 添加字段 $tableName.{$columnInfo['name']} 成功");
                    $result['success']++;
                } catch (Exception $e) {
                    $this->error("❌ 添加字段 $tableName.{$columnInfo['name']} 失败: ".$e->getMessage());
                    $result['failure']++;
                }
            }
        }

        return $result;
    }

    /**
     * 构建字段定义
     */
    private function buildColumnDefinition(array $column): string
    {
        $definition = $column['type'];

        if (! $column['nullable']) {
            $definition .= ' NOT NULL';
        }

        if ($column['default'] !== null) {
            if (strtolower($column['default']) == 'current_timestamp') {
                $definition .= " DEFAULT {$column['default']}";
            } else {
                $definition .= " DEFAULT '{$column['default']}'";
            }
        }

        if ($column['extra']) {
            $definition .= ' '.$column['extra'];
        }

        if ($column['comment']) {
            $definition .= " COMMENT '{$column['comment']}'";
        }

        return $definition;
    }

    /**
     * 修改不同的字段
     */
    private function modifyDifferentColumns(array $differentColumns, string $connection = 'mysql'): array
    {
        $result = ['success' => 0, 'failure' => 0];

        foreach ($differentColumns as $tableName => $columns) {
            foreach ($columns as $columnInfo) {
                try {
                    $columnDef = $this->buildColumnDefinition($columnInfo['expected']);
                    $sql = "ALTER TABLE `$tableName` MODIFY COLUMN `{$columnInfo['name']}` $columnDef";

                    DB::connection($connection)->statement($sql);

                    $currentType = $columnInfo['current']['type'];
                    $expectedType = $columnInfo['expected']['type'];
                    $this->info("✅ 修改字段 $tableName.{$columnInfo['name']} ($currentType → $expectedType) 成功");
                    $result['success']++;
                } catch (Exception $e) {
                    $this->error("❌ 修改字段 $tableName.{$columnInfo['name']} 失败: ".$e->getMessage());
                    $result['failure']++;
                }
            }
        }

        return $result;
    }

    /**
     * 修复索引
     */
    private function fixIndexes(array $indexDifferences, string $connection = 'mysql'): array
    {
        $result = ['success' => 0, 'failure' => 0];

        foreach ($indexDifferences as $tableName => $differences) {
            // 删除多余的索引
            if (! empty($differences['extra'])) {
                foreach ($differences['extra'] as $indexName => $indexInfo) {
                    try {
                        DB::connection($connection)->statement("ALTER TABLE `$tableName` DROP INDEX `$indexName`");
                        $this->info("✅ 删除索引 $tableName.$indexName 成功");
                        $result['success']++;
                    } catch (Exception $e) {
                        $this->error("❌ 删除索引 $tableName.$indexName 失败: ".$e->getMessage());
                        $result['failure']++;
                    }
                }
            }

            // 修改不同的索引
            if (! empty($differences['different'])) {
                foreach ($differences['different'] as $indexName => $indexInfo) {
                    try {
                        // 先删除旧索引
                        DB::connection($connection)->statement("ALTER TABLE `$tableName` DROP INDEX `$indexName`");

                        // 创建新索引
                        $this->createIndex($tableName, $indexName, $indexInfo['expected'], $connection);

                        $this->info("✅ 更新索引 $tableName.$indexName 成功");
                        $result['success']++;
                    } catch (Exception $e) {
                        $this->error("❌ 更新索引 $tableName.$indexName 失败: ".$e->getMessage());
                        $result['failure']++;
                    }
                }
            }

            // 添加缺失的索引
            if (! empty($differences['missing'])) {
                foreach ($differences['missing'] as $indexName => $indexInfo) {
                    try {
                        $this->createIndex($tableName, $indexName, $indexInfo, $connection);
                        $this->info("✅ 创建索引 $tableName.$indexName 成功");
                        $result['success']++;
                    } catch (Exception $e) {
                        $this->error("❌ 创建索引 $tableName.$indexName 失败: ".$e->getMessage());
                        $result['failure']++;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * 创建索引
     */
    private function createIndex(string $tableName, string $indexName, array $indexInfo, string $connection = 'mysql'): void
    {
        $columns = '`'.implode('`, `', $indexInfo['columns']).'`';
        $unique = $indexInfo['unique'] ? 'UNIQUE' : '';

        if ($indexName === 'PRIMARY') {
            $sql = "ALTER TABLE `$tableName` ADD PRIMARY KEY ($columns)";
        } else {
            $sql = "ALTER TABLE `$tableName` ADD $unique INDEX `$indexName` ($columns)";
        }

        DB::connection($connection)->statement($sql);
    }
}
