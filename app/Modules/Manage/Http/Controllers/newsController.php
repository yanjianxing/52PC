<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Http\Controllers\ManageController;
use App\Modules\Manage\Model\ArticleCategoryModel;
use App\Modules\Manage\Model\ArticleModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Manage\Model\SeoRelatedModel;
use App\Modules\Manage\Model\SpecialModel;
use App\Modules\Manage\Model\SpecialNewsModel;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Manage\Model\attachmentModel;
use App\Http\Requests;
use App\Modules\Manage\Http\Requests\newsRequest;
use App\Modules\Employ\Models\AnswerModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use Theme;
use DB;
use Illuminate\Support\Facades\Auth;


class newsController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->set('manageType', 'news');

    }

    /**
     * 后台资讯列表
     * @param int $upID 资讯分类父id
     * @param Request $request
     * @return mixed
     */
    public function newsList(Request $request, $upID)
    {
        //查询分类名称
        $title = ArticleCategoryModel::where('id',$upID)->first()->cate_name;
        if($upID == 1){
            $this->theme->setTitle('资讯管理');
        }elseif($upID == 3){
            $this->theme->setTitle('页脚管理');
        }
        $arr = $request->all();
        $upID = intval($upID);
        //查询所有分类
        $m = ArticleCategoryModel::get()->toArray();
        $res = ArticleCategoryModel::_reSort($m,$upID);
        //文章列表
        $articleList = ArticleModel::whereRaw(' 1 = 1');
        //分类筛选
        if ($request->get('catID')) {
            //获取所有子分类
            $r = ArticleCategoryModel::_children($m, $request->get('catID'));
            if (empty($r)) {
                $articleList = $articleList->where('article.cat_id', $request->get('catID'));
            } else {
                $catIds = array_merge($r, array($request->get('catID')));
                $articleList = $articleList->whereIn('article.cat_id', $catIds);
            }
        } else {
            //获取所有子分类
            $r = ArticleCategoryModel::_children($m, $upID);
            $catIds = array_merge($r, array($upID));
            $articleList = $articleList->whereIn('article.cat_id', $catIds);

        }
        //编号筛选
        if ($request->get('artID')) {
            $articleList = $articleList->where('article.id', $request->get('artID'));
        }
        //标题筛选
        if ($request->get('title')) {
            $articleList = $articleList->where('article.title', 'like', "%" . e($request->get('title')) . '%');
        }
        //作者筛选
        if ($request->get('author')) {
            $articleList = $articleList->where('article.author', 'like', '%' . e($request->get('author')) . '%');
        }
        //发布人筛选
        if ($request->get('publisher')) {
            $articleList = $articleList->where('article.publisher', 'like', '%' . e($request->get('publisher')) . '%');
        }
        //来源筛选
        if (!empty($request->get('form'))){
            $articleList = $articleList->where('article.from', $request->get('form'));
        }

        //推荐筛选
        if (!empty($request->get('recommended'))){

            if($request->get('recommended') == '1' && $request->get('recommendedvalue') > 0 ){
                $articleList = $articleList->where('article.recommended1', $request->get('recommendedvalue'));
            }elseif($request->get('recommended') == '2' &&  $request->get('recommendedvalue') > 0  ){
                $articleList = $articleList->where('article.recommended2', $request->get('recommendedvalue'));
            }
            
        }

        //状态筛选
        if ($request->get('status') != '' && intval($request->get('status')) >= 0 && intval($request->get('status')) < 99 && is_numeric($request->get('status')) ){
            $articleList = $articleList->where('article.status', $request->get('status'));
        }
        //服务筛选
        if ($request->get('is_free')){
            $articleList = $articleList->where('article.is_free', $request->get('is_free'));
        }
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $articleList = $articleList->where('article.created_at','>',$start);
        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $articleList = $articleList->where('article.created_at','<',$end);
        }
        $by = $request->get('by') ? $request->get('by') : 'article.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $list = $articleList
            ->leftjoin('article_category as c', 'article.cat_id', '=', 'c.id')
            ->leftjoin('cate as ca', 'article.cate_id', '=', 'ca.id')
            ->leftjoin('users','users.id','=','article.user_id')
            ->where('article.is_delete','0')
            ->select('users.name as uname','article.id','article.user_id','article.articlefrom','article.keywords', 'article.cat_id',  'article.title', 'article.view_times', 'article.from',  'article.is_free',  'article.status','article.recommended1','article.recommended2', 'article.author', 'article.publisher', 'article.created_at', 'c.cate_name as cate_name', 'ca.name as application_name')
            ->orderBy($by, $order)->paginate($paginate);
        $listArr = $list->toArray();
        foreach ($listArr['data'] as $key => $value) {
            $listArr['data'][$key]['commentcount'] = DB::table("answer")->where('article_id','=',$value['id'])->count();
            $listArr['data'][$key]['commentcheck'] = DB::table("answer")->where('article_id','=',$value['id'])->where('status','=','0')->count();
        }
        //获取所有的seo 标签
        $seoList=SeoModel::all();
        $arrstatus=[0,2,3,4];
        $data = array(
            'merge' => $arr,
            'upID' => $upID,
            'artID' => $request->get('artID'),
            'title' => $request->get('title'),
            'catID' => $request->get('catID'),
            'author' => $request->get('author'),
            'publisher' => $request->get('publisher'),
            'form' => $request->get('form'),
            'status' => $request->get('status'),
            'paginate' => $request->get('paginate'),
            'order' => $request->get('order'),
            'by' => $request->get('by'),
            'recommended'   => $request->get('recommended'),
            'recommendedvalue'   => $request->get('recommendedvalue'),
            'article_data' => $listArr,
            'article' => $list,
            'category' => $res,
            'seoList' =>$seoList,
            'arrstatus' =>$arrstatus,
        );
        return $this->theme->scope('manage.newsList', $data)->render();
    }

    /**
     * 资讯列表是否推荐1
     * @param Request $request
     * @return mixed
     */
    public function recommended1($id, $action){
            switch ($action){
            case 'yes':
                $recommended1 = 1;
                break;
            case 'no':
                $recommended1 = 2;
                break;
            }
            if($action=='no'){
                $recommended1 = ArticleModel::where('id', $id)->update(['recommended1' => $recommended1]);
                if ($recommended1){
                    return back()->with(['message' => '操作成功']);
                }
            }elseif($action=='yes'){
                //统计推荐1的数量
                $countnum=DB::table('article')->where('status',1)->where('recommended1',1)->count();
                if($countnum==4 ){
                    return back()->with(['message' => '推荐已满，请小主先忍痛割爱至少一个']);
                }elseif($countnum<4){
                    $arrstatus=[0,2,3,4];
                    //查看是否有资格
                    $status=ArticleModel::where('id',$id)->pluck('status');
                    if(in_array($status,$arrstatus)){
                        return back()->with(['message' => '该文章暂未获得推荐资格']);
                    }else{
                        $recommended1 = ArticleModel::where('id', $id)->update(['recommended1' => $recommended1]);
                        if ($recommended1)
                            return back()->with(['message' => '操作成功']);
                    }
                }
            }
    }

    /**
     * 资讯列表是否推荐2
     * @param Request $request
     * @return mixed
     */
    public function recommended2($id, $action){
        switch ($action){
            case 'yes':
                $recommended2 = 1;
                break;
            case 'no':
                $recommended2 = 2;
                break;
        }
        if($action=='no'){
            $recommended2 = ArticleModel::where('id', $id)->update(['recommended2' => $recommended2]);
            if ($recommended2){
                return back()->with(['message' => '操作成功']);
            }
        }elseif($action=='yes'){
            //统计推荐1的数量
            $countnum=DB::table('article')->where('status',1)->where('recommended2',1)->count();
            if($countnum==7 ){
                return back()->with(['message' => '推荐已满，请小主先忍痛割爱至少一个']);
            }elseif($countnum<7){
                $arrstatus=[0,2,3,4];
                //查看是否有资格
                $status=ArticleModel::where('id',$id)->pluck('status');
                if(in_array($status,$arrstatus)){
                    return back()->with(['message' => '该文章暂未获得推荐资格']);
                }else{
                    $recommended2 = ArticleModel::where('id', $id)->update(['recommended2' => $recommended2]);
                    if ($recommended2)
                        return back()->with(['message' => '操作成功']);
                }
            }
        }
    }



    /**
     * 编辑资讯视图
     * @param Request $request
     * @param $id 资讯id
     * @param $upID 资讯分类父id
     * @return mixed
     */
    public function editNews(Request $request, $id, $upID)
    {
        $id = intval($id);
        $upID = intval($upID);
        //查询分类名称
        $res = ArticleCategoryModel::where('pid','=','1')->orderBy('display_order','asc')->get()->toArray();
        $parentCate = ArticleCategoryModel::where('id',$upID)->first();
        //查询应用领域分类
        $application = CateModel::where('pid','=','0')->where('type','=','1')->get()->toArray();
        //查询技术分类
        $technology = CateModel::where('pid','=','0')->where('type','=','2')->get()->toArray();
        //根据文章id查询文章信息
        $article = ArticleModel::where('id', $id)->first();
        //查看附件
        $attres = '';
        if(!empty($article['attachment_id'])){
            $attres = attachmentModel::where('id',$article['attachment_id'])->first()->url;
        }
        //获取所有的seo 标签
        $seoList=SeoModel::all();
        //获取项目seo标签
        $taskSeo=SeoRelatedModel::where("related_id",$id)->where("type",3)->lists("seo_id")->toArray();
        $data = array(
            'article' => $article,
            'parent_cate' => $parentCate,
            'upID' => $upID,
            'cate' => $res,
            'application' => $application,
            'technology' => $technology,
            'attres'    =>  $attres,
            'nowtime'   =>  date("Y年m月d日",time()),
            'seoList'      =>$seoList,
            'taskSeo'   =>$taskSeo,
        );
        $this->theme->setTitle('资讯编辑');
        return $this->theme->scope('manage.editNews', $data)->render();
    }

    /**
     * 编辑资讯
     * @param Request $request
     */
    public function postEditNews(Request $request)
    {
        
        $data = $request->except('_token','seo_laber');
        /*图片上传*/
        $file = $request->file('pic');
        $pic = '';
        if ($file) {
           $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            $pic = $result['data']['url'];
        }
        /*end图片*/
        /*处理附件*/
        $attachmentres = $request->file('attachment');
        $attresult = '';
        if($attachmentres){
            $attachmentresult = \FileClass::uploadFile($attachmentres,'sys');
            $attachmentresult = json_decode($attachmentresult,true);
            $attachmentdata['url'] = $attachmentresult['data']['url'];
            $attachmentdata['type'] = $attachmentresult['data']['type'];
            $attachmentdata['size'] = intval($attachmentresult['data']['size']);
            $attachmentdata['disk'] = $attachmentresult['data']['disk'];
            $attachmentdata['user_id'] = $attachmentresult['data']['user_id'];
            $attachmentdata['name'] = $attachmentresult['data']['name'];
            $attachmentdata['created_at'] = date('Y-m-d H:i:s',time());
            $attres = attachmentModel::create($attachmentdata);
            $attresult = json_decode($attres,true);
        }
        
        /*end附件*/
        switch($data['upID']){
            case 1:
                $url = '/manage/newsList/';
                break;
            case 3:
                $url = '/manage/articleFooter/';
                break;
            default:
                $url = '/manage/newsList/';
        }
        $data['content'] = isset($data['content'])?htmlspecialchars($data['content']):'';
        if($request->get('content')){
            if(mb_strlen($data['content']) > 4294967295/3){
                $error['content'] = '文章内容太长，建议减少上传图片';
                if (!empty($error)) {
                    return redirect('/manage/addArticle')->withErrors($error);
                }
            }
        }
        if($data['start']){
            $data['start'] = preg_replace('/([\x80-\xff]*)/i', '', $data['start']);
            $data['start'] = date('Y-m-d H:i:s',strtotime($data['start']));
        }
        $arr = array(
            'title' => isset($data['title'])?$data['title']:'',
            'cat_id' => isset($data['catID'])?$data['catID']:'',
            'author' => isset($data['author'])?$data['author']:'',
            'publisher' => isset($data['publisher'])?$data['publisher']:'',
            'pr_leader' => isset($data['pr_leader'])?$data['pr_leader']:'',
            'cate_id' => isset($data['cate_id'])?$data['cate_id']:'',
            'technology_id' => isset($data['technology'])?$data['technology']:'',
            'online_time' => isset($data['start'])?$data['start']:date('Y-m-d H:i:s',time()),
            'content' => isset($data['content'])?$data['content']:'',
            'summary' => isset($data['summary'])?$data['summary']:'',
            'keywords' => isset($data['keywords'])?$data['keywords']:'',
            'updated_at' => date('Y-m-d H:i:s',time()),
        );
        if($pic){
            $arr['pic'] = $pic;
        }
        if($attresult){
            $arr['attachment_id'] = $attresult['id'];
        }
        $arr['articlefrom'] = isset($data['articlefrom'])?$data['articlefrom']:'';
        if($data['articlefrom']==2){
            $arr['reprint_url'] = isset($data['reprint_url'])?$data['reprint_url']:'';
        }else{
            $arr['reprint_url'] = '';
        }
        //修改信息
        $res = ArticleModel::where('id', $data['artID'])->update($arr);
        //删除seo标签
        SeoRelatedModel::where("related_id",$data['artID'])->where("type",3)->delete();
        //添加seo标签
        if($request->get('seo_laber')){
            SeoModel::seoHandle(1,3,[$data['artID']],$request->get('seo_laber'));
        }
        if ($res) {
            return redirect($url . $data['upID'])->with(array('message' => '操作成功'));
        }
        return redirect($url . $data['upID'])->with(array('message' => '操作失败！'));
    }

    /**
     * 新建资讯视图
     * @param $upID 资讯分类父id
     * @param Request $request
     * @return mixed
     */
    public function addNews(Request $request, $upID)
    {
        $upID = intval($upID);
        //查询分类名称
        $title = ArticleCategoryModel::where('id',$upID)->first()->cate_name;
        $this->theme->setTitle('资讯新建');
        //查询应用领域分类
        $application = CateModel::where('pid','=','0')->where('type','=','1')->get()->toArray();
        //查询技术分类
        $technology = CateModel::where('pid','=','0')->where('type','=','2')->get()->toArray();
        //查询所有分类
        $res = ArticleCategoryModel::where('pid','=','1')->orderBy('display_order','asc')->get()->toArray();
        $parentCate = ArticleCategoryModel::where('id',$upID)->first();
        $data = array(
            'cate' => $res,
            'parent_cate' => $parentCate,
            'upID' => $upID,
            'application' => $application,
            'technology' => $technology,
            'nowtime'   =>  date("Y年m月d日",time())
        );
        return $this->theme->scope('manage.addNews', $data)->render();
    }

    /**
     * 新建资讯文章
     * @param newsRequest $request
     */
    public function postNews(Request $request)
    {
        //获取文章信息
        $data = $request->except('_token', 'pic','upID');
        $upID = $request->get('upID');
        switch($upID){
            case 1:
                $url = '/manage/newsList/';
                break;
            case 3:
                $url = '/manage/articleFooter/';
                break;
            default:
                $url = '/manage/newsList/';
        }
        /*图片上传*/
        $file = $request->file('pic');
        if (!$file) {
            $news = ArticleModel::where('id','=',$request->get('artID'))->first();
            $pic = $news['pic'];
        }else{
            $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            $pic = $result['data']['url'];
        }
        /*end图片*/
        /*处理附件*/
        $attachmentres = $request->file('attachment');
        $attresult = '';
        if($attachmentres){
            $attachmentresult = \FileClass::uploadFile($attachmentres,'sys');
            $attachmentresult = json_decode($attachmentresult,true);
            $attachmentdata['url'] = $attachmentresult['data']['url'];
            $attachmentdata['type'] = $attachmentresult['data']['type'];
            $attachmentdata['size'] = intval($attachmentresult['data']['size']);
            $attachmentdata['disk'] = $attachmentresult['data']['disk'];
            $attachmentdata['user_id'] = $attachmentresult['data']['user_id'];
            $attachmentdata['name'] = $attachmentresult['data']['name'];
            $attachmentdata['created_at'] = date('Y-m-d H:i:s',time());
            $attres = attachmentModel::create($attachmentdata);
            $attresult = json_decode($attres,true);
        }
        if($attresult){
            $data1['attachment_id'] = $attresult['id'];
        }
        /*end附件*/
        /*处理时间*/
        if(!empty($data['online_time'])){
            $arr = date_parse_from_format('Y年m月d日 H:i:s',$data['online_time']);
            $onlinestrtotime = mktime(0,0,0,$arr['month'],$arr['day'],$arr['year']);
            $data['online_time'] = date("Y-m-d H:i:s",$onlinestrtotime);
        }
        $data1['cat_id'] = isset($data['catID'])?$data['catID']:'';
        $data1['title'] = isset($data['title'])?$data['title']:'';
        $data1['author'] = isset($data['author'])?$data['author']:'';
        $data1['publisher'] = isset($data['publisher'])?$data['publisher']:'';
        $data1['pr_leader'] = isset($data['pr_leader'])?$data['pr_leader']:'';
        $data1['cate_id'] = isset($data['cate_id'])?$data['cate_id']:'';
        $data1['technology_id'] = isset($data['technology'])?$data['technology']:'';
        $data1['summary'] = isset($data['summary'])?$data['summary']:'';
        $data1['pic'] = $pic;
        $data1['online_time'] = empty($data['online_time'])?date('Y-m-d H:i:s',time()) : $data['online_time'];
        $data1['keywords'] = isset($data['keywords'])?$data['keywords']:'';
        $data1['status'] = '1';
        $data1['created_at'] = date('Y-m-d H:i:s',time());
        $data1['updated_at'] = date('Y-m-d H:i:s',time());
        $data1['content'] = isset($data['content'])?htmlspecialchars($data['content']):'';
        if($request->get('content')){
            if(mb_strlen($data['content']) > 4294967295/3){
                $error['content'] = '文章内容太长，建议减少上传图片';
                if (!empty($error)) {
                    return redirect('/manage/addNews')->withErrors($error);
                }
            }
        }
        $data1['articlefrom'] = isset($data['articlefrom'])?$data['articlefrom']:'';
        if($data['articlefrom']==2){
            $data1['reprint_url'] = isset($data['reprint_url'])?$data['reprint_url']:'';
        }
        //添加信息
        $res = ArticleModel::create($data1);
        if ($res) {
            return redirect($url . $upID)->with(array('message' => '操作成功'));
        }
        return false;
    }

    /**
     * 批量删除
     * @param Request $request
     */
    public function allDelete(Request $request)
    {
        $data = $request->except('_token');

        $res = ArticleModel::destroy($data);
        if ($res) {
            return redirect()->to('/manage/article/1')->with(array('message' => '操作成功'));
        }
        return redirect()->to('/manage/article/1')->with(array('message' => '操作失败'));
    }

    /*更改状态*/
    public function changestatus($id , $upID , $status,Request $request){
        $all = $request->all();//接收审核失败原因
        $upID = intval($upID);
        switch($upID){
            case 1:
                $url = '/manage/newsList/';
                break;
            case 2:
                $url = '/manage/articleFooter/';
                break;
            case 81:
                $url = '/manage/specialList/';
                break;
            default:
                $url = '/manage/newsList/';
        }
        if($status == 4){
            $action = 1;
        }else{
            $action = $status;
        }
        $arr = array('status'=>$action);
        if($upID == 81 ){
            $result = SpecialModel::where('id',$id)->update($arr);
        }else{
            $reason = isset($all['reason']) ? $all['reason'] : '';//.不通过原因并将审核失败原因填入表中
            $arr = array('status'=>$action,'reason'=>$reason,'verified_at'   => date('Y-m-d H:i:s'));
            $result = ArticleModel::where('id',$id)->update($arr);
        }
        if($status == 1 || $status == 2){
            if($upID == 1){
                $article = ArticleModel::find($id);
                $userInfo = UserModel::find($article['user_id']);
               if($userInfo){
                   $user = [
                       'uid'    => $userInfo->id,
                       'email'  => $userInfo->email,
                       'mobile' => $userInfo->mobile
                   ];
                   $from = $status == 1 ? '已通过' : '审核失败';
                   $templateArr = [
                       'username'      => $userInfo->name,
                       'title'         => $article->title,
                       'action'          => $from,

                   ];
                   \MessageTemplateClass::sendMessage('article_check',$user,$templateArr,$templateArr);
               }
            }
        }
        
        if (!$result) {
            return redirect()->to($url . $upID)->with(array('message' => '操作失败'));
        }
        
        if($upID == 1 && $status == 1){ //资讯审核通过自动发放
            UserModel::sendfreegrant($article['user_id'],8);
        }
        if($upID == 1 && $status == 2){ //资讯审核不通过 退款
            $refund = [
                    'uid' => $article['user_id'],
                    'article_id' => $id,
                    'price' => $article['price']
                ];
            $article_refund = ArticleModel::refund($refund);
            if(!$article_refund){
                return redirect()->to($url . $upID)->with(array('message' => '退款失败'));
            }
        }
        return redirect()->to($url . $upID)->with(array('message' => '操作成功'));
    }

    /*
     * 专题列表
     * @param int $upID 资讯分类article_category父id
     * @param Request $request
    */
    public function specialList(Request $request, $upID){
        //查询分类名称
        $title = ArticleCategoryModel::where('id',$upID)->first()->cate_name;
        
        $arr = $request->all();
        $upID = intval($upID);
        //查询所有分类
        $m = ArticleCategoryModel::get()->toArray();
        $res = ArticleCategoryModel::_reSort($m,$upID);
        //文章列表
        $SpecialList = SpecialModel::whereRaw(' 1 = 1');
        
        //编号筛选
        if ($request->get('specialID')) {
            $SpecialList = $SpecialList->where('special.id', $request->get('specialID'));
        }
        //标题筛选
        if ($request->get('title')) {
            $SpecialList = $SpecialList->where('special.title', 'like', "%" . e($request->get('title')) . '%');
        }
        //发布人筛选
        /*if ($request->get('author')) {
            $SpecialList = $SpecialList->where('article.author', 'like', '%' . e($request->get('author')) . '%');
        }*/
        
        //状态筛选
        if ($request->get('status') != '' && intval($request->get('status')) >= 0 && intval($request->get('status')) < 99 && is_numeric($request->get('status')) ){
            $SpecialList = $SpecialList->where('special.status', $request->get('status'));
        }
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $SpecialList = $SpecialList->where('special.created_at','>',$start);
        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $SpecialList = $SpecialList->where('special.created_at','<',$end);
        }
        $by = $request->get('by') ? $request->get('by') : 'special.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;


        $list = $SpecialList->orderBy($by, $order)->paginate($paginate);
        $listArr = $list->toArray();
        $data = array(
            'merge' => $arr,
            'upID' => $upID,
            'specialID' => $request->get('specialID'),
            'title' => $request->get('title'),
            'author' => $request->get('author'),
            'status' => $request->get('status'),
            'paginate' => $request->get('paginate'),
            'order' => $request->get('order'),
            'by' => $request->get('by'),
            'special_data' => $listArr,
            'special' => $list,
            'category' => $res

        );
        $this->theme->setTitle($title);
        return $this->theme->scope('manage.specialList', $data)->render();
    }
    /**
     * 新建专题视图
     * @param $upID 资讯分类父id
     * @param Request $request
     * @return mixed
     */
    public function addSpecial(Request $request, $upID)
    {
        $upID = intval($upID);
        //查询分类名称
        $this->theme->setTitle('专题新建');
        //查询所有分类
        $m = ArticleCategoryModel::get()->toArray();
        $res = ArticleCategoryModel::_reSort($m,$upID);
        $parentCate = ArticleCategoryModel::where('id',$upID)->first();
        $data = array(
            'cate' => $res,
            'parent_cate' => $parentCate,
            'upID' => $upID,
            
        );
        return $this->theme->scope('manage.addSpecial', $data)->render();
    }

    /**
     * 保存专题文章
     * @param newsRequest $request
     */
    public function postSpecial(Request $request){
        //获取文章信息
        $data = $request->except('_token', 'pic','upID');
        $upID = $request->get('upID');
        $url = '/manage/specialList/';
        /*图片上传*/
        $file = $request->file('pic');
        $pic = '';
        if ($file) {
            $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            $pic = $result['data']['url'];
        }
        $filebanner = $request->file('banner');
        $banner = '';
        if ($filebanner) {
            $result = \FileClass::uploadFile($filebanner,'sys');
            $result = json_decode($result,true);
            $banner = $result['data']['url'];
        }
        /*end图片*/
        /*end附件*/
       
        $data1['title'] = isset($data['title'])?$data['title']:'';
        $data1['logo'] = $pic;
        $data1['banner'] = $banner;
        $data1['introduction'] = isset($data['introduction'])?$data['introduction']:'';
        $data1['news_id'] = isset($data['specialnews_id'])?$data['specialnews_id']:'';
        $data1['edit_by'] = isset($data['edit_by'])?$data['edit_by']:'';
        $data1['status'] = '1';
        $data1['created_at'] = date("Y-m-d H:i:s",time());
        $data1['is_open'] = 1;
        //添加信息
        $res = SpecialModel::create($data1);
        if ($res) {
            return redirect($url . $upID)->with(array('message' => '操作成功'));
        }
        return false;
    }

     /**
     * 专题文章ajax
     * @param $news_id 专题文章ID
     * @param Request $request
     * @return mixed
     */
    public function ajaxnews(Request $request){
        $articleid = intval($request->get('articleid'));
        $news_id = $request->get('news_id');

        if(empty($articleid)){
            return response()->json(['code'=>'1','errMsg'=>'请填写已有文章id']);
        }
        if(strpos("$news_id","$articleid") !== false){
            return response()->json(['code'=>'2','errMsg'=>'请勿添加重复文章！']);
        }
        $article = ArticleModel::where('id',$articleid)->first();
        if(empty($article)){
            return response()->json(['code'=>'3','errMsg'=>'请填写已有文章id']);
        }
        if($article['status'] != '1'){
            return response()->json(['code'=>'3','errMsg'=>'请填写审核通过的文章id']);
        }
        //保存到关联专题文章表 special_news
        $data['logo'] = $article['pic'];
        $data['title'] = $article['title'];
        $data['introduction'] = $article['summary'];
        $data['url'] = "/news/".$article['id'];
        $data['created_at'] = date("Y-m-d H:i:s",time());
        $data['article_id'] = $articleid;
        $res = SpecialNewsModel::create($data);
        if($res){
            $article['specialnews_id'] = $res['id'];
            return response()->json(['code'=>'200','successdata'=>$article]);
        }else{
            return response()->json(['code'=>'1','errMsg'=>'未知错误！请联系管理员']);
        }  
    }

    public function editSpecial(Request $request, $id , $upID){
        $id = intval($id);
        $upID = intval($upID);
        //根据id获取专题信息
        $specialres = SpecialModel::where('id',$id)->first()->toArray();
        //获取专题文章信息
        $news_id = $specialres['news_id'];  // special_news表ID  多个用,隔开
        $specialnewres = explode(",",$news_id);
        $arr = array();
        $newsid = '';
        if($news_id){
            foreach ($specialnewres as $key => $value) {
                $result = SpecialNewsModel::where('id',$value)->first()->toArray();
                $arr[$key] = $result;
            }
            foreach ($arr as $k => $v) {
                $arr_n = count($arr)-1;
                if($arr_n == $k){
                    $newsid .= $v['article_id'];
                }else{
                    $newsid .= $v['article_id'].',';
                }
                
            }
        }
        $data = array(
            'upID' => $upID,
            'special' => $specialres,
            'special_news' => $arr,
            'specialnews_id' =>  $news_id,
            'news_id' =>  $newsid,
        );
        $this->theme->setTitle('专题编辑');
        return $this->theme->scope('manage.editSpecial', $data)->render();
    }

    /**
     * 保存编辑专题文章
     * @param newsRequest $request
     */
    public function postEditSpecial(Request $request)
    {
        //获取文章信息
        $data = $request->except('_token', 'pic','upID');
        $upID = $request->get('upID');
        $url = '/manage/specialList/';
        // print_r($data);exit;
        /*图片上传*/

        $file = $request->file('pic');
        $pic = '';
        if ($file) {
           $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            $pic = $result['data']['url'];
        }
        $filebanner = $request->file('banner');
        $banner = '';
        if ($filebanner) {
            $result = \FileClass::uploadFile($filebanner,'sys');
            $result = json_decode($result,true);
            $banner = $result['data']['url'];
        }
        /*end图片*/
       if($pic){
            $data1['logo'] = $pic;
        }
        if($banner){
            $data1['banner'] = $banner;
        }
        //根据specialid获取专题信息
        $specialres = SpecialModel::where('id',$data['specialid'])->first()->toArray();
        if(isset($data['specialnews_id']) && $data['specialnews_id'] != $specialres['news_id']){
            $data1['news_id'] = $data['specialnews_id'];
        }
        $data1['introduction'] = isset($data['introduction'])?$data['introduction']:'';
        $data1['edit_by'] = isset($data['edit_by'])?$data['edit_by']:'';
        $data1['title'] = isset($data['title'])?$data['title']:'';
        $data1['created_at'] = date("Y-m-d H:i:s",time());
        $data1['is_open'] = 1;
        //添加信息
        $res = SpecialModel::where('id',$data['specialid'])->update($data1);
        if ($res) {
            return redirect($url . $upID)->with(array('message' => '操作成功'));
        }
        return false;
    }

    public function editSpecialNews(Request $request, $id, $specialid, $upID){
        $id = intval($id);
        $upID = intval($upID);
        $specialid = intval($specialid);
        $result = SpecialNewsModel::where('id',$id)->first();
        $data = array(
            'upID' => $upID,
            'SpecialNews' => $result,
            'specialid' => $specialid
        );
        $this->theme->setTitle('专题文章编辑');
        return $this->theme->scope('manage.editSpecialNews', $data)->render();
    }

    public function postEditSpecialNews(Request $request)
    {
        //获取文章信息
        $data = $request->except('_token', 'pic','upID','specialid');
        $upID = $request->get('upID');
        $specialid = $request->get('specialid');
        $url = '/manage/editSpecial/';
        /*图片上传*/
        $file = $request->file('pic');
        $pic = '';
        if ($file) {
           $result = \FileClass::uploadFile($file,'sys');
            $result = json_decode($result,true);
            $pic = $result['data']['url'];
        }
        /*end图片*/
       if($pic){
            $data1['logo'] = $pic;
        }
        $specialres = SpecialNewsModel::where('id',$data['id'])->first()->toArray();
        $data1['title'] = $data['title'];
        $data1['introduction'] = $data['introduction'];
        $data1['created_at'] = date("Y-m-d H:i:s",time());
        //更新信息
        $res = SpecialNewsModel::where('id',$data['id'])->update($data1);
        if ($res) {
            return redirect($url . $specialid .'/'. $upID)->with(array('message' => '操作成功'));
        }
        return false;
    }

    public function ajaxspecialnews(Request $request){
        $id = intval($request->get('id'));
        $specialid = intval($request->get('specialid'));
        $upID = intval($request->get('upID'));
        $url = '/manage/editSpecial/';
        if(empty($id) || empty($specialid)){
            return response()->json(['errMsg'=>'参数错误！']);
        }
        DB::beginTransaction();
        $specialres =  SpecialModel::where('id',$specialid)->first();
        if($specialres && $specialres['news_id']){
            $newsidresult = explode(',',$specialres['news_id']);
            foreach ($newsidresult as $key=>$value) {
                if($id == $value){
                    unset($newsidresult[$key]);
                }
            }
            $data['news_id'] = $newsidresult ? implode(',', $newsidresult) : '';
            // 更新专题news_id包含的文章ID
            $specialres = SpecialModel::where('id',$specialid)->update($data);
        }else{
            $specialres = true;
        }
        // 删除文章
        $specialnewsres = SpecialNewsModel::where('id',$id)->delete();
        if(!$specialnewsres || $specialres === 'false'){
            DB::rollback();
            return response()->json(['errMsg'=>'删除失败！请联系管理员']);
        }
        DB::commit();
        return 200;
    }

    //资讯评论列表
    public function getNewsComment(Request $request){
        $data = $request->all();
        $query = AnswerModel::whereRaw("1=1");
        if($request->get('articleid')){
            $query = $query->where('article_id','=',$request->get('articleid'));
        }
        //标题筛选
        if ($request->get('title')) {
            $query = $query->where('article.title', 'like', "%" . e($request->get('title')) . '%');
        }
        
        //状态筛选
        if ($request->get('status') != '' && intval($request->get('status')) >= 0 && intval($request->get('status')) < 99 && is_numeric($request->get('status')) ){
            $query = $query->where('answer.status', $request->get('status'));
        }
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $query = $query->where('answer.time','>',$start);
        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $query = $query->where('answer.time','<',$end);
        }
        //排序筛选
        $orderBy = 'answer.time';
        if ($request->get('orderBy')) {
            $orderBy = $request->get('orderBy');
        }
        $orderByType = 'desc';
        if ($request->get('orderByType')) {
            $orderByType = $request->get('orderByType');
        }
        //分页条数筛选
        $page_size = 10;
        if ($request->get('pageSize')) {
            $page_size = $request->get('pageSize');
        }
        $comments = $query
            ->leftjoin("users","users.id","=","answer.uid")
            ->leftjoin("article","article.id","=","answer.article_id")
            ->select("answer.*","users.name","article.title")
            ->orderBy($orderBy, $orderByType)
            ->paginate($page_size);
        $commentsArr = $comments->toArray();

        $view = [
            'data' => $commentsArr,
            'comment' => $comments,
            'merge' => $data,
            'status' => $request->get('status'),
            'title' => $request->get('title'),
            'articleid' => $request->get('articleid'),
        ];
        $this->theme->setTitle('资讯评论');
        return $this->theme->scope('manage.newsCommentList', $view)->render();
    }

    /*更改状态*/
    public function newsChangeStatus($id='' ,$status,$del="",Request $request){
        $url = '/manage/getNewsComment';
        $arr = array('status'=>$status);
        if($status==2){
            $arr['reason'] = $request->get('reason') ? $request->get('reason') : '';
        }
        $answer=AnswerModel::leftJoin("article","answer.article_id","=","article.id")->where("answer.id",$id)
                     ->select("answer.*","article.title","article.user_id")->first();
        if($id && $del==1){
            $result = AnswerModel::where('id',$id)->delete(); 
        }else{
           $result =DB::transaction(function() use($arr,$status,$answer,$id){
               AnswerModel::where('id',$id)->update($arr);
               //发送站内信
               $userInfo=UserModel::find($answer['uid']);
               $user = [
                   'uid'    =>$userInfo->id,
                   'email'  => $userInfo->email,
                   'mobile' => $userInfo->mobile
               ];
               $templateArr = [
                   'username' =>$userInfo->name,
                   'title'    =>"<a href='/news/".$answer['article_id']."' target='_blank'>".$answer['title']."</a>",
                   
               ];
               if($status == 2){
                    $templateArr['reason'] = $arr['reason'];
               }
               $codeName=$status==2?"article_reviews_failure":"article_reviews_success";
               \MessageTemplateClass::sendMessage($codeName,$user,$templateArr,$templateArr);
              //项目文章发布人发送短信
              if($status ==1){

                  $userArticle=UserModel::find($answer['user_id']);
                  $user1 = [
                      'uid'    =>$userArticle->id,
                      'email'  => $userArticle->email,
                      'mobile' => $userArticle->mobile
                  ];
                  $templateArr1 = [
                      'username' =>$userArticle->name,
                      'title'     =>"<a href='/news/".$answer['article_id']."' target='_blank'>".$answer['title']."</a>",
                  ];
                  \MessageTemplateClass::sendMessage("article_comment",$user1,$templateArr1,$templateArr1);
              }
           });
            $result = is_null($result) ? true : false;
        }
        if (!$result) {
            return redirect()->to($url)->with(array('message' => '操作失败'));
        }
        return redirect()->to($url)->with(array('message' => '操作成功'));
    }

}
