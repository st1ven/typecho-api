<?php
header('Access-Control-Allow-Origin: *');
class JSON_Action extends Typecho_Widget implements Widget_Interface_Do {
    private $db;
    private $res;
    const LACK_PARAMETER = 'Not found';
    public function __construct($request, $response, $params = NULL) {
        parent::__construct($request, $response, $params);
        $this->db  = Typecho_Db::get();
        if (method_exists($this, $this->request->type)) {
            call_user_func(array(
                $this,
                $this->request->type
            ));
        } else {
            $this->defaults();
        }
    }
    private function defaults() {
        $this->export(null);
    }
    private function count() {
        $select = $this->db->select("COUNT(*) AS counts")->from('table.contents')->where('type = ?', 'post')->where('status = ?', 'publish')->where('created < ?', time());
        $res    = $this->db->fetchRow($select);
        return $this->export($res);
    }
    //参数 pageSize, page, authorId, created, cid, category, commentsNumMax, commentsNumMin, allowComment
    private function posts() {
        $pageSize = (int) self::GET('pageSize', 5);
        $page     = (int) self::GET('page', 1);
        $authorId = self::GET('authorId', 0);
        $offset   = $pageSize * ($page - 1);
        $select   = $this->db->select('cid', 'title', 'created', 'type', 'slug', 'text', 'authorId')->from('table.contents')->where('type = ?', 'post')->where('status = ?', 'publish')->where('created < ?', time())->order('table.contents.created', Typecho_Db::SORT_DESC)->offset($offset)->limit($pageSize);
        // 根据cid偏移获取文章
        if (isset($_GET['cid'])) {
            $cid = self::GET('cid');
            $select->where('cid > ?', $cid);
        }
        // 根据时间偏移获取文章
        if (isset($_GET['created'])) {
            $created = self::GET('created');
            $select->where('created > ?', $created);
        }
        // 根据分类或标签获取文章
        if (isset($_GET['category']) || isset($_GET['tag'])) {
            $name     = isset($_GET['category']) ? $_GET['category'] : $_GET['tag'];
            $resource = $this->db->fetchAll($this->db->select('cid')->from('table.relationships')->join('table.metas', 'table.metas.mid = table.relationships.mid', Typecho_Db::LEFT_JOIN)->where('slug = ?', $name));
            $cids     = array();
            foreach ($resource as $item) {
                $cids[] = $item['cid'];
            }
            $select->where('cid IN ?', $cids);
        }
        // 是否限制作者
        if ($authorId) {
            $select->where('authorId = ?', $authorId);
        }
        //是否限制评论数
        if (isset($_GET['commentsNumMax'])) {
            $commentsNumMax = self::GET('commentsNumMax');
            $select->where('commentsNum < ?', $commentsNumMax);
        }
        if (isset($_GET['commentsNumMin'])) {
            $commentsNumMin = self::GET('commentsNumMin');
            $select->where('commentsNum > ?', $commentsNumMin);
        }
        //是否限制获取允许评论的文章
        if (isset($_GET['allowComment'])) {
            $allowComment = self::GET('allowComment');
            $select->where('allowComment = ?', $allowComment);
        }
        $posts  = $this->db->fetchAll($select);
        $result = array();
        foreach ($posts as $post) {
            $post        = $this->widget("Widget_Abstract_Contents")->push($post);
            $post['author'] = $this->db->fetchAll($this->db->select('uid', 'name', 'mail', 'url', 'screenName')->from('table.users')->where('uid = ?', $post['authorId']));
            $post['tag'] = $this->db->fetchAll($this->db->select('name')->from('table.metas')->join('table.relationships', 'table.metas.mid = table.relationships.mid', Typecho_DB::LEFT_JOIN)->where('table.relationships.cid = ?', $post['cid'])->where('table.metas.type = ?', 'tag'));
            $post['thumb'] = $this->db->fetchAll($this->db->select('str_value')->from('table.fields')->where('cid = ?', $post['cid']))?$this->db->fetchAll($this->db->select('str_value')->from('table.fields')->where('cid = ?', $post['cid'])):array(array("str_value"=>"https://ww4.sinaimg.cn/large/a15b4afegw1f8sqaz6y6bj20go06j0u2"));
            $result[]    = $post;
        }
        $this->export($result);
    }
    //参数 content
    private function pageList() {
        $content = self::GET('content', false);
        $this->widget('Widget_Contents_Page_List')->to($pages);
        $pageList = array();
        while ($pages->next()) {
            $page = array(
                "title" => $pages->title,
                "slug" => $pages->slug,
                "permalink" => $pages->permalink
            );
            if ($content) {
                $page['content']     = $pages->text;
                $page['commentsNum'] = $pages->commentsNum;
            }
            $pageList[] = $page;
        }
        $this->export($pageList);
    }
    //参数 cid, slug
    private function single() {
        if (!isset($_GET['cid']) && !isset($_GET['slug'])) {
            $this->export(self::LACK_PARAMETER, 404);
        }
        $select = $this->db->select('cid', 'title', 'created', 'type', 'slug', 'text')->from('table.contents');
        if (isset($_GET['cid'])) {
            $select->where('cid = ?', $_GET['cid']);
        }
        if (isset($_GET['slug'])) {
            $select->where('slug = ?', $_GET['slug']);
        }
        $post = $this->db->fetchRow($select);
        $post = $this->widget("Widget_Abstract_Contents")->push($post);
        $this->export($post);
    }
    private function post() {
        $this->single();
    }
    //参数 authorId, cid
    private function relatedPosts() {
        if (!isset($_GET['authorId']) && !isset($_GET['cid'])) {
            $this->export(self::LACK_PARAMETER, 404);
        }
        $authorId  = self::GET('authorId');
        $limit     = self::GET('limit', 5);
        $type      = self::GET('type');
        $cid       = self::GET('cid');
        $parameter = http_build_query(compact("authorId", "limit", "type", "cid"));
        $this->widget("Widget_Contents_Related_Author", $parameter)->to($posts);
        $relatedPosts = array();
        while ($posts->next()) {
            $relatedPosts[] = array(
                "title" => $posts->title,
                "text" => $posts->text,
                "created" => $posts->created,
                "permalink" => $posts->permalink,
                "author" => $posts->author
            );
        }
        $this->export($relatedPosts);
    }
    //参数 pageSize
    private function recentPost() {
        $pageSize = self::GET('pageSize', 10);
        $this->widget("Widget_Contents_Post_Recent", "pageSize={$pageSize}")->to($post);
        $recentPost = array();
        while ($post->next()) {
            $recentPost[] = array(
                "cid" => $post->cid,
                "title" => $post->title,
                "permalink" => $post->permalink
            );
        }
        $this->export($recentPost);
    }
    //参数 pageSize, parentId, ignoreAuthor, showCommentOnly
    private function recentComments() {
        $pageSize        = self::GET('pageSize', 10);
        $parentId        = self::GET('parentId', 0);
        $ignoreAuthor    = self::GET('ignoreAuthor', false);
        $showCommentOnly = self::GET('showCommentOnly', false);
        $parameter       = http_build_query(compact("pageSize", "parentId", "ignoreAuthor", "showCommentOnly"));
        $this->widget("Widget_Comments_Recent", $parameter)->to($comments);
        $recentComments = array();
        while ($comments->next()) {
            $recentComments[] = array(
                "permalink" => $comments->permalink,
                "authorId" => $comments->authorId,
                "ownerId" => $comments->ownerId,
                "created" => $comments->created,
                "author" => $comments->author,
                "parent" => $comments->parent,
                "agent" => $comments->agent,
                "coid" => $comments->coid,
                "mail" => $comments->mail,
                "type" => $comments->type,
                "text" => $comments->text,
                "url" => $comments->url,
                "cid" => $comments->cid,
                "ip" => $comments->ip
            );
        }
        $this->export($recentComments);
    }
    //参数 ignore, childMode
    private function categoryList() {
        $ignores   = explode(',', self::GET('ignore'));
        $childMode = self::GET('childMode', false);
        $this->widget("Widget_Metas_Category_List")->to($category);
        $categoryList = array();
        if ($childMode) {
            $parent = array();
        }
        while ($category->next()) {
            if (in_array($category->mid, $ignores)) {
                continue;
            }
            $cate = array(
                "name" => $category->name,
                "slug" => $category->slug,
                "count" => $category->count,
                "permalink" => $category->permalink
            );
            if ($childMode) {
                $mid      = $category->mid;
                $parentId = $category->parent;
                if ($parentId) {
                    $parent[$parentId]['child'][] = $cate;
                } else {
                    $parent[$mid] = isset($parent[$mid]) ? array_merge($parent[$mid], $cate) : $cate;
                }
            } else {
                $categoryList[] = $cate;
            }
        }
        if ($childMode) {
            $categoryList = array_values($parent);
        }
        $this->export($categoryList);
    }
    //参数 sort, count, ignoreZeroCount, desc, limit
    private function tagCloud() {
        $sort            = self::GET('sort', 'count');
        $ignoreZeroCount = self::GET('ignoreZeroCount', false);
        $desc            = self::GET('desc', true);
        $limit           = self::GET('limit', 0);
        $tagCloud        = array();
        $this->widget("Widget_Metas_Tag_Cloud", "sort={$sort}&ignoreZeroCount={$ignoreZeroCount}&desc={$desc}&limit={$limit}")->to($tags);
        while ($tags->next()) {
            $tagCloud[] = array(
                "name" => $tags->name,
                "count" => $tags->count,
                "permalink" => $tags->permalink
            );
        }
        $this->export($tagCloud);
    }
    //参数 format, type, limit
    private function archive() {
        $format   = self::GET('format', 'Y-m');
        $type     = self::GET('type', 'month');
        $limit    = self::GET('limit', 0);
        $select   = $this->db->select('created')->from('table.contents')->where('type = ?', 'post')->where('table.contents.status = ?', 'publish')->where('table.contents.created < ?', time())->order('table.contents.created', Typecho_Db::SORT_DESC);
        $resource = $this->db->query($select);
        $offset   = 0;
        $result   = array();
        while ($post = $this->db->fetchRow($resource)) {
            $timeStamp = $post['created'] + $offset;
            $date      = date($format, $timeStamp);
            if (isset($result[$date])) {
                $result[$date]['count']++;
            } else {
                $result[$date]['year']  = date('Y', $timeStamp);
                $result[$date]['month'] = date('m', $timeStamp);
                $result[$date]['day']   = date('d', $timeStamp);
                $result[$date]['date']  = $date;
                $result[$date]['count'] = 1;
            }
        }
        if ($limit > 0) {
            $result = array_slice($result, 0, $limit);
        }
        foreach ($result as &$row) {
            $row['permalink'] = Typecho_Router::url('archive_' . $type, $row, $this->widget('Widget_Options')->index);
        }
        $this->export($result);
    }
    public function export($data = array(), $status = 200) {
        $this->response->throwJson(array(
            'status' => $status,
            'data' => $data
        ));
        exit;
    }
    public static function GET($key, $default = '') {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }
    public function action() {
        $this->on($this->request);
    }
}
