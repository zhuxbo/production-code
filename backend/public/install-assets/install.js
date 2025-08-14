// 切换显示/隐藏扩展列表
function toggleExtensions() {
    var list = document.getElementById("extensions-list");
    list.style.display = list.style.display === "none" ? "block" : "none";
}

// 切换显示/隐藏权限列表
function togglePermissions() {
    var list = document.getElementById("permissions-list");
    list.style.display = list.style.display === "none" ? "block" : "none";
}

// 切换显示/隐藏必需PHP函数列表
function toggleRequiredFunctions() {
    var list = document.getElementById("required-functions-list");
    list.style.display = list.style.display === "none" ? "block" : "none";
}

// 切换显示/隐藏可选PHP函数列表
function toggleOptionalFunctions() {
    var list = document.getElementById("optional-functions-list");
    list.style.display = list.style.display === "none" ? "block" : "none";
}

// 切换显示/隐藏PHP函数列表 (向后兼容)
function toggleFunctions() {
    toggleRequiredFunctions();
    toggleOptionalFunctions();
}

// 准备并提交安装表单
function prepareAndSubmitInstall(button) {
    button.disabled = true;
    button.innerHTML = '<span class="loading-spinner"></span> 正在安装...';
    button.style.background = '#9ca3af';
    button.style.cursor = 'not-allowed';

    // 显示日志框
    var logDiv = document.getElementById("install-log-div");
    if (logDiv) {
        logDiv.style.display = "block";
        logDiv.innerHTML = `
            <div style="text-align: center; padding: 20px;">
                <div class="progress-ring">
                    <div class="progress-ring-fill"></div>
                </div>
                <div style="margin-top: 15px; color: #e2e8f0;">正在准备安装...</div>
            </div>
        `;
    }

    var form = button.form;
    if (form) {
        form.submit();
    } else {
        console.error("找不到安装按钮的表单");
    }
}

// 处理安装日志中的HTML转义
function decodeHtmlEntities(text) {
    var textArea = document.createElement("textarea");
    textArea.innerHTML = text;
    return textArea.value;
}

// 当文档加载完成时运行
document.addEventListener("DOMContentLoaded", function () {
    // 配置表单提交事件监听
    var configForm = document.getElementById("config-form");
    if (configForm) {
        configForm.addEventListener("submit", function () {
            console.log("表单提交");
            // 显示提交前的值
            const dbHost = document.getElementById("db_host").value;
            const dbDatabase = document.getElementById("db_database").value;
            console.log("数据库主机:", dbHost);
            console.log("数据库名称:", dbDatabase);
        });
    }

    // 如果存在安装日志区域，确保它是可见的
    var installLog = document.getElementById("install-log-div");
    if (installLog && window.location.search.includes("install=1")) {
        installLog.style.display = "block";

        // 监视DOM变化，确保新添加的元素样式正确
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === "childList") {
                    // 对所有新添加的成功消息应用样式
                    var successElements =
                        installLog.querySelectorAll(".success");
                    successElements.forEach(function (element) {
                        if (element.tagName === "DIV") {
                            element.style.color = "#4CAF50";
                            element.style.borderLeft = "4px solid #4CAF50";
                            element.style.padding = "5px 10px";
                            element.style.margin = "5px 0";
                            element.style.display = "block";
                            element.style.background = "transparent";
                        }
                    });

                    // 对所有新添加的警告消息应用样式
                    var warningElements =
                        installLog.querySelectorAll(".warning");
                    warningElements.forEach(function (element) {
                        if (element.tagName === "DIV") {
                            element.style.color = "#FFC107";
                            element.style.borderLeft = "4px solid #FFC107";
                            element.style.padding = "5px 10px";
                            element.style.margin = "5px 0";
                            element.style.display = "block";
                            element.style.background = "transparent";
                        }
                    });

                    // 对所有新添加的错误消息应用样式
                    var errorElements = installLog.querySelectorAll(".error");
                    errorElements.forEach(function (element) {
                        if (element.tagName === "DIV") {
                            element.style.color = "#F44336";
                            element.style.borderLeft = "4px solid #F44336";
                            element.style.padding = "5px 10px";
                            element.style.margin = "5px 0";
                            element.style.display = "block";
                            element.style.background = "transparent";
                        }
                    });
                }
            });
        });

        // 配置和启动监视器
        observer.observe(installLog, { childList: true, subtree: true });
    }

    // 格式化输出中的pre标签内容，确保转义字符正确显示
    document.querySelectorAll("#install-log-div pre").forEach(function (pre) {
        pre.textContent = decodeHtmlEntities(pre.innerHTML);
    });
});
