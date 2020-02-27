<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Modules\Manage\Http\Requests\CategoryRequest;
use App\Modules\Manage\Model\ArticleCategoryModel;
use App\Modules\Manage\Model\ArticleModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ArticleCategoryController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->set('manageType', 'articleCategory');

    }

    /**
     * 文章分类
     * @param $upID 文章分类父id
     * @param Request $request
     * @return mixed
     */
    public function categoryList(Request $request,$upID)
    {
        $upID = intval($upID);
        $isChild = false;
        $parentID = ArticleCategoryModel::getParentId($upID);
        //查询分类名称
        $title = ArticleCategoryModel::where('id',$upID)->first()->cate_name;
        if($title == '帮助中心' && $parentID == 3){
            $isChild = true;
        }
        if($upID == 1){
            $this->theme->setTitle('文章分类');
        }
        elseif($upID == 2){
            $this->theme->setTitle('帮助中心');
        }
        elseif($upID == 3){
            $this->theme->setTitle('页脚分类');
        }
        $parentCate = ArticleCategoryModel::where('id',$upID)->first();
        $categoryList = ArticleCategoryModel::whereRaw('1 = 1');
        if ($request->get('catID')) {
            $categoryList = $categoryList->where('pid', $request->get('catID'));
        } else {
            $categoryList = $categoryList->where('pid', $upID);
        }
        $by = $request->get('by') ? $request->get('by') : 'display_order';
        $order = $request->get('order') ? $request->get('order') : 'asc';
        $paginate = 10;
        $list = $categoryList ->select('id','pid','cate_name','display_order','updated_at')
            ->orderBy($by, $order)->paginate($paginate);
        $data = array(
            'category_data' => $list,
            'parent_cate' => $parentCate,
            'catID' => $request->get('catID'),
            'upID' => $upID,
            'by' => $by,
            'order' => $order,
            'paginate' => $paginate,
            'isChild' => $isChild,
            'parentID' => $parentID
        );
        return $this->theme->scope('manage.categorylist', $data)->render();
    }

    /**
     * 获取子分类
     * @param $id 父级分类id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getChildCateList($id)
    {
        $upID = ArticleCategoryModel::getParentId($id);
        switch($upID){
            case 1:
                $url = '/manage/categoryList/';
                break;
            case 3:
                $url = '/manage/categoryFooterList/';
                break;
            default:
                $url = '/manage/categoryList/';
        }
        return redirect()->to($url.$id);
    }

    /**
     * @param $id 分类id
     * @param $upID 分类父id
     * @return
     */
    public function categoryDelete($id,$upID)
    {
        $id = intval($id);
        $upID = intval($upID);
        $parentId = ArticleCategoryModel::getParentId($id);
        switch($parentId){
            case 1:
                $url = '/manage/categoryList/';
                break;
            case 3:
                $url = '/manage/categoryFooterList/';
                break;
            default:
                $url = '/manage/categoryList/';
        }
        //查询该分类下是否有文章
        $article = ArticleModel::where('cat_id',$id)->get()->toArray();
        //查询分类有无子分类
        $cate = ArticleCategoryModel::where('pid',$id)->get()->toArray();
        if(!empty($article)) {
            return redirect($url.$upID)->with(array('message' => '该分类下有文章不能删除'));
        }elseif(!empty($cate)){
            return redirect($url.$upID)->with(array('message' => '该分类下有子分类不能删除'));
        }
        $result = ArticleCategoryModel::where('id',$id)->delete();
        if(!$result) {
            return redirect()->to($url.$upID)->with(array('message' => '操作失败'));
        }
        return redirect()->to($url.$upID)->with(array('message' => '操作成功'));
    }

    /**
     * 批量删除
     * @param Request $request
     * @return mixed
     */
    public function cateAllDelete(Request $request)
    {
        $data = $request->except('_token','upID');
        $upID = $request->get('upID');
        //查询分类下有无文章
        $article = ArticleModel::whereIn('id',$data)->get()->toArray();
        //查询分类有无子分类
        $cate = ArticleCategoryModel::whereIn('pid',$data)->get()->toArray();
        if(!empty($article)) {
            return redirect('/manage/categoryList/'.$upID)->with(array('message' => '该分类下有文章不能删除'));
        }elseif(!empty($cate)){
            return redirect('/manage/categoryList/'.$upID)->with(array('message' => '该分类下有子分类不能删除'));
        }
        $res = ArticleCategoryModel::destroy($data);
        if(!$res) {
            return redirect()->to('/manage/categoryList/'.$upID)->with(array('message' => '操作失败'));
        }
        return redirect()->to('/manage/categoryList/'.$upID)->with(array('message' => '操作成功'));
    }

    /**
     * 判断父级分类是资讯中心或页脚配置
     * @param $upID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function add($upID)
    {
        $parentId = ArticleCategoryModel::getParentId($upID);
        switch($parentId){
            case 1:
                $url = '/manage/categoryAdd/';
                break;
            case 2:
                $url = '/manage/categoryFooterAdd/';
                break;
            default:
                $url = '/manage/categoryAdd/';
        }
        return redirect()->to($url.$upID);
    }

    /**
     * 判断父级分类是资讯中心或页脚配置
     * @param $id
     * @param $upID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function edit($id,$upID)
    {
        $parentId = ArticleCategoryModel::getParentId($id);
        switch($parentId){
            case 1:
                $url = '/manage/categoryEdit/';
                break;
            case 2:
                $url = '/manage/categoryFooterEdit/';
                break;
            default:
                $url = '/manage/categoryEdit/';
        }
        return redirect()->to($url.$id.'/'.$upID);
    }

    /**
     * 新建资讯分类视图
     * @param Request $request
     * @param $upID 分类父id
     * @return mixed
     */
    public function categoryAdd(Request $request,$upID)
    {
        $upID = intval($upID);
        //查询该父id的信息
        $cate = ArticleCategoryModel::where('id',$upID)->get()->toArray();
        $this->theme->setTitle($cate[0]['cate_name']);
        $data = array(
            'catName' => $cate[0]['cate_name'],
            'upID' => $upID
        );
        $this->theme->setTitle('分类新建');
        return $this->theme->scope('manage.addcategory',$data)->render();
    }

    /**
     * 新建分类
     * @param CategoryRequest $request
     * @return
     */
    public function postCategory(CategoryRequest $request)
    {
        //获取分类信息
        $data = $request->all();
        $data['cate_name'] = $data['catName'];
        $parentId = ArticleCategoryModel::getParentId($data['upID']);
        switch($parentId){
            case 1:
                $url = '/manage/categoryList/';
                break;
            case 2:
                $url = '/manage/categoryFooterList/';
                break;
            default:
                $url = '/manage/categoryList/';
        }
        //添加信息
        $res = ArticleCategoryModel::createCategory($data);
        if($res) {
            return redirect($url.$data['upID'])->with(array('message' => '操作成功'));
        }
    }

    /**
     * 编辑分类视图
     * @param Request $request
     * @param $id 分类id
     */
    public function categoryEdit(Request $request,$id,$upID)
    {
        //查询分类名称
        $title = ArticleCategoryModel::where('id',$upID)->first()->cate_name;
        $this->theme->setTitle('分类编辑');
        $id = intval($id);
        $category = ArticleCategoryModel::where('id',$id)->get()->toArray();
        //查询根分类id
        $pid = ArticleCategoryModel::getParentId($id);
        $m = ArticleCategoryModel::get()->toArray();
        $upIDs = ArticleCategoryModel::_reSort($m,$pid);
        $data = array(
            'catID' => $category[0]['id'],
            'catName' => $category[0]['cate_name'],
            'displayOrder' => $category[0]['display_order'],
            'upID' => $upID,
            'upIDs' => $upIDs,
            'title'=>$title
        );
        return $this->theme->scope('manage.editcategory',$data)->render();
    }

    /**
     * 编辑分类
     * @param CategoryRequest $request
     * @return
     */
    public function postEditCategory(CategoryRequest $request)
    {
        //获取分类信息
        $data = $request->all();
        $parentId = ArticleCategoryModel::getParentId($data['upID']);
        switch($parentId){
            case 1:
                $url = '/manage/categoryList/';
                break;
            case 2:
                $url = '/manage/categoryFooterList/';
                break;
            default:
                $url = '/manage/categoryList/';
        }
        $arr = array(
             'pid' => $data['upID'],
             'cate_name' => $data['catName'],
             'display_order' => $data['displayOrder'],
             'updated_at' => date('Y-m-d H;i:s',time())
        );
        //修改信息
        $res = ArticleCategoryModel::where('id',$data['catID'])->update($arr);
        if($res) {
            return redirect($url.$data['upID'])->with(array('message' => '操作成功'));
        }
    }

}

