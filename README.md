# typecho-api
基于Typecho的JSON API插件

---
# 安装方法

下载后将JSON文件夹上传至/usr/plugins文件夹内

登录Typecho博客后台，顶部导航栏-控制台-插件-找到JSON API-启用

大功告成！试试访问 http://[example].com/api/[action] 吧！

---
# 使用方法

Api地址：http://[example].com/api/[action]，替换[action]为下面列出的方法，更多介绍可以看[JSON/Action.php](https://github.com/szj1006/typecho-api/blob/master/JSON/Action.php)中的备注

例如：[https://sangsir.com/api/posts](https://sangsir.com/api/posts)

1. count
2. posts //参数 pageSize, page, authorId, created, cid, category, commentsNumMax, commentsNumMin, allowComment
3. pageList //参数 content
4. single,post //参数 cid, slug
5. relatedPosts //参数 authorId, cid
6. recentPost //参数 pageSize
7. recentComments //参数 pageSize, parentId, ignoreAuthor, showCommentOnly
8. categoryList //参数 ignore, childMode
9. tagCloud //参数 sort, count, ignoreZeroCount, desc, limit
10. archive //参数 format, type, limit

---
# 感谢

https://github.com/lizheming/JSON
