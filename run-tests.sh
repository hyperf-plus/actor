#!/bin/bash

# Actor系统测试运行脚本
# 使用方法：./run-tests.sh [选项]

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 默认值
SUITE=""
COVERAGE=false
PERFORMANCE=false
VERBOSE=false
FILTER=""

# 显示帮助信息
show_help() {
    echo "Actor系统测试运行脚本"
    echo ""
    echo "使用方法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  -h, --help          显示帮助信息"
    echo "  -s, --suite SUITE   运行指定测试套件 (Unit|Integration|Performance|FaultTolerance|Feature)"
    echo "  -c, --coverage      生成代码覆盖率报告"
    echo "  -p, --performance   运行性能测试"
    echo "  -v, --verbose       详细输出"
    echo "  -f, --filter FILTER 过滤测试方法"
    echo ""
    echo "示例:"
    echo "  $0                    # 运行所有基础测试"
    echo "  $0 -s Unit           # 运行单元测试"
    echo "  $0 -s Integration    # 运行集成测试"
    echo "  $0 -p                # 运行性能测试"
    echo "  $0 -c                # 运行测试并生成覆盖率报告"
    echo "  $0 -f testActorCreation  # 运行包含特定名称的测试"
    echo ""
}

# 解析命令行参数
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            exit 0
            ;;
        -s|--suite)
            SUITE="$2"
            shift 2
            ;;
        -c|--coverage)
            COVERAGE=true
            shift
            ;;
        -p|--performance)
            PERFORMANCE=true
            shift
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        -f|--filter)
            FILTER="$2"
            shift 2
            ;;
        *)
            echo -e "${RED}未知选项: $1${NC}"
            show_help
            exit 1
            ;;
    esac
done

# 检查依赖
echo -e "${BLUE}检查测试环境...${NC}"

if ! command -v php &> /dev/null; then
    echo -e "${RED}错误: PHP 未安装${NC}"
    exit 1
fi

if [ ! -f "vendor/bin/phpunit" ]; then
    echo -e "${YELLOW}PHPUnit 未找到，正在安装依赖...${NC}"
    composer install --dev
fi

# 创建必要的目录
mkdir -p build/logs
mkdir -p .phpunit.cache

# 构建PHPUnit命令
PHPUNIT_CMD="vendor/bin/phpunit"

if [ "$VERBOSE" = true ]; then
    PHPUNIT_CMD="$PHPUNIT_CMD --verbose"
fi

if [ -n "$FILTER" ]; then
    PHPUNIT_CMD="$PHPUNIT_CMD --filter $FILTER"
fi

if [ "$COVERAGE" = true ]; then
    echo -e "${BLUE}启用代码覆盖率报告...${NC}"
    PHPUNIT_CMD="$PHPUNIT_CMD --coverage-html build/coverage --coverage-clover build/logs/clover.xml"
fi

# 运行测试
echo -e "${GREEN}开始运行Actor系统测试...${NC}"
echo "=============================================="

if [ "$PERFORMANCE" = true ]; then
    echo -e "${YELLOW}运行性能测试...${NC}"
    $PHPUNIT_CMD --group performance
elif [ -n "$SUITE" ]; then
    echo -e "${YELLOW}运行 $SUITE 测试套件...${NC}"
    $PHPUNIT_CMD --testsuite $SUITE
else
    echo -e "${YELLOW}运行基础测试（除性能测试外）...${NC}"
    $PHPUNIT_CMD --exclude-group performance
fi

# 测试结果处理
TEST_EXIT_CODE=$?

if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✅ 所有测试通过！${NC}"
    
    # 显示覆盖率信息
    if [ "$COVERAGE" = true ]; then
        echo -e "${BLUE}📊 代码覆盖率报告已生成: build/coverage/index.html${NC}"
    fi
    
    # 显示日志信息
    echo -e "${BLUE}📝 测试日志文件:${NC}"
    ls -la build/logs/ 2>/dev/null || echo "无日志文件生成"
    
else
    echo ""
    echo -e "${RED}❌ 测试失败，退出代码: $TEST_EXIT_CODE${NC}"
    exit $TEST_EXIT_CODE
fi

# 性能测试建议
if [ "$PERFORMANCE" = true ]; then
    echo ""
    echo -e "${YELLOW}📈 性能测试建议:${NC}"
    echo "- 在生产环境中运行性能测试获得更准确的结果"
    echo "- 使用 PHP OPcache 可以提升性能"
    echo "- 监控内存使用情况，避免内存泄漏"
fi

# 集成测试建议
if [ "$SUITE" = "Integration" ] || [ -z "$SUITE" ]; then
    echo ""
    echo -e "${YELLOW}🔗 集成测试提示:${NC}"
    echo "- 集成测试模拟真实的Actor交互场景"
    echo "- 确保所有组件能够正确协同工作"
fi

echo ""
echo -e "${GREEN}测试完成！${NC}" 