<?php
$files = [
    // 'statamic/public/build' => 'build',
    // 'statamic/public/assets' => 'assets',
    // 'statamic/public/vendor' => 'vendor',
];

header('Content-Type: text/plain; charset=utf-8');

foreach ($files as $target => $link) {
    // 检查目标文件是否存在
    if (file_exists($target)) {
        // 检查链接是否存在
        if (file_exists($link)) {
            // 检查是否是软链接
            if (is_link($link)) {
                // 删除现有的软链接
                unlink($link);
                echo "已删除软链接: $link\n";
            } else {
                echo "文件 $link 已存在且不是软链接，跳过创建。\n";
                continue;
            }
        }
        // 创建新的软链接
        symlink($target, $link);
        echo "已创建软链接: $link -> $target\n";
    } else {
        echo "目标文件 $target 不存在，无法创建软链接。\n";
    }
}
