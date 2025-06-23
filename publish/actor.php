<?php
declare(strict_types=1);

/**
 * Actor系统配置
 */

use function Hyperf\Support\env;

return [
    // 基础配置
    'enabled' => env('ACTOR_ENABLED', true),
    
    // 工作进程数量
    'worker_processes' => env('ACTOR_WORKER_PROCESSES', 4),
    
    // 批处理大小
    'batch_size' => env('ACTOR_BATCH_SIZE', 100),
    
    // 邮箱配置
    'mailbox' => [
        // 邮箱容量
        'capacity' => env('ACTOR_MAILBOX_CAPACITY', 1000),
        
        // 邮箱类型
        'type' => env('ACTOR_MAILBOX_TYPE', 'default'),
    ],
    
    // 监督配置
    'supervision' => [
        // 监督检查间隔（秒）
        'interval' => env('ACTOR_SUPERVISION_INTERVAL', 10),
        
        // 重启策略
        'restart_strategy' => env('ACTOR_RESTART_STRATEGY', 'one_for_one'),
        
        // 最大重启次数
        'max_restarts' => env('ACTOR_MAX_RESTARTS', 3),
        
        // 重启时间窗口（秒）
        'restart_window' => env('ACTOR_RESTART_WINDOW', 60),
    ],
    
    // 内存配置
    'memory' => [
        // 单个进程最大内存（字节）
        'max_memory' => env('ACTOR_MAX_MEMORY', 512 * 1024 * 1024), // 512MB
        
        // 系统最大内存（字节）
        'max_system_memory' => env('ACTOR_MAX_SYSTEM_MEMORY', 1024 * 1024 * 1024), // 1GB
    ],
    
    // 健康检查配置
    'health_check' => [
        // 健康检查间隔（秒）
        'interval' => env('ACTOR_HEALTH_CHECK_INTERVAL', 30),
        
        // 启用健康检查
        'enabled' => env('ACTOR_HEALTH_CHECK_ENABLED', true),
    ],
    
    // 统计配置
    'stats' => [
        // 统计收集间隔（秒）
        'interval' => env('ACTOR_STATS_INTERVAL', 60),
        
        // 启用统计收集
        'enabled' => env('ACTOR_STATS_ENABLED', true),
        
        // 统计存储方式
        'storage' => env('ACTOR_STATS_STORAGE', 'memory'), // memory, redis, database
    ],
    
    // 游戏配置
    'game' => [
        // 默认房间配置
        'default_room' => [
            'max_players' => env('GAME_DEFAULT_MAX_PLAYERS', 4),
            'min_players' => env('GAME_DEFAULT_MIN_PLAYERS', 2),
            'auto_start' => env('GAME_DEFAULT_AUTO_START', false),
            'timeout' => env('GAME_DEFAULT_TIMEOUT', 300), // 5分钟
        ],
        
        // 玩家配置
        'player' => [
            'idle_timeout' => env('GAME_PLAYER_IDLE_TIMEOUT', 600), // 10分钟
            'max_reconnect_time' => env('GAME_PLAYER_MAX_RECONNECT_TIME', 60), // 1分钟
        ],
        
        // 匹配配置
        'matchmaking' => [
            'enabled' => env('GAME_MATCHMAKING_ENABLED', true),
            'timeout' => env('GAME_MATCHMAKING_TIMEOUT', 30), // 30秒
            'max_skill_difference' => env('GAME_MATCHMAKING_MAX_SKILL_DIFF', 100),
        ],
    ],
    
    // 日志配置
    'logging' => [
        // 日志级别
        'level' => env('ACTOR_LOG_LEVEL', 'info'),
        
        // 启用详细日志
        'verbose' => env('ACTOR_LOG_VERBOSE', false),
        
        // 日志通道
        'channel' => env('ACTOR_LOG_CHANNEL', 'actor'),
    ],
    
    // 性能配置
    'performance' => [
        // 启用性能监控
        'monitoring' => env('ACTOR_PERFORMANCE_MONITORING', true),
        
        // 慢消息阈值（毫秒）
        'slow_message_threshold' => env('ACTOR_SLOW_MESSAGE_THRESHOLD', 100),
        
        // 启用消息追踪
        'message_tracing' => env('ACTOR_MESSAGE_TRACING', false),
    ],
    
    // 集群配置（为未来扩展预留）
    'cluster' => [
        // 启用集群模式
        'enabled' => env('ACTOR_CLUSTER_ENABLED', false),
        
        // 节点名称
        'node_name' => env('ACTOR_CLUSTER_NODE_NAME', 'node-1'),
        
        // 种子节点
        'seed_nodes' => explode(',', env('ACTOR_CLUSTER_SEED_NODES', '')),
    ],
]; 