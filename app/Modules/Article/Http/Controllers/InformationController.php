<?php
namespace App\Modules\Article\Http\Controllers;

use App\Http\Controllers\IndexController;
use App\Http\Requests;
use App\Modules\Article\Model\ArticleModel;
use App\Modules\Manage\Model\ArticleCategoryModel;
use Illuminate\Http\Request;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Advertisement\Model\RePositionModel;
use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\Manage\Model\ConfigModel;
use Cache;

class InformationController extends IndexController
{
	public function __construct()
    {
        parent::__construct();

        $this->initTheme('main');
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function  index(Request $request)
    {
        //seo配置信息
        $seoConfig = ConfigModel::getConfigByType('seo');
		//获取导航名称
		$NavName= \CommonClass::getNavName('/article');
		if(!$NavName){
			$NavName="资讯中心";
		}
        if($request->get('catID')){
            //查询分类名称
            $nameO = ArticleCategoryModel::where('id',$request->get('catID'))->first();
            if(!empty($nameO)){
                $name = $nameO ->cate_name;
                $this->theme->setTitle($name);
                $this->theme->set('keywords',$name);
                $this->theme->set('description',$name);
            }else{
                if(!empty($seoConfig['seo_article']) && is_array($seoConfig['seo_article'])){
                    $this->theme->setTitle($seoConfig['seo_article']['title']);
                    $this->theme->set('keywords',$seoConfig['seo_article']['keywords']);
                    $this->theme->set('description',$seoConfig['seo_article']['description']);
                }else{
                    $this->theme->setTitle('资讯中心|众包威客资讯信息中心_KPPW演示');
                    $this->theme->set('keywords','资讯中心,威客资讯,众包资讯,信息中心,KPPW演示');
                    $this->theme->set('description','客客专业开源建站系统，资讯中心，众包威客资讯信息中心。');
                }
            }

        }else{
            if(!empty($seoConfig['seo_article']) && is_array($seoConfig['seo_article'])){
                $this->theme->setTitle($seoConfig['seo_article']['title']);
                $this->theme->set('keywords',$seoConfig['seo_article']['keywords']);
                $this->theme->set('description',$seoConfig['seo_article']['description']);
            }else{
                $this->theme->setTitle('资讯中心|众包威客资讯信息中心_KPPW演示');
                $this->theme->set('keywords','资讯中心,威客资讯,众包资讯,信息中心,KPPW演示');
                $this->theme->set('description','客客专业开源建站系统，资讯中心，众包威客资讯信息中心。');
            }
        }

        $merge = $request->all();
        $upIDCate = ArticleCategoryModel::where('pid',0)->where('cate_name','资讯中心')->first();
        if(!empty($upIDCate)){
            $upID = $upIDCate->id;
            $category = ArticleCategoryModel::where('pid',$upID)->orderBY('display_order','ASC')->get();
            $catID = $request->get('catID') ? $request->get('catID') : $category[0]['id'];
            $list = ArticleModel::where('cat_id',$catID)->orderby('created_at','desc')->orderby('display_order','ASC')->paginate(10);
            $listArr = $list->toArray();
        }

        //热门文章
        $reTarget = RePositionModel::where('code','ARTICLEINFO_SIDE')->where('is_open','1')->select('id','name')->first();
        if($reTarget->id){
            $recommend = RecommendModel::getRecommendInfo($reTarget->id)->select('*')->get();
            if(count($recommend)){
                $HotList = $recommend;
            }
            else{
                $HotList = [];
            }
        }

        //资讯中心顶部广告
        $ad = AdTargetModel::getAdInfo('NEWSLIST_TOP');

        //资讯中心右上方广告
        $rightAd = AdTargetModel::getAdInfo('NEWSLIST_RIGHT_TOP');

        $view = [
            'merge' => $merge,
            'list'=>$listArr,
            'list_obj' => $list,
            'catID'=>$catID,
            'category'=>$category,
            'hotlist'=>$HotList,
            'ad'=>$ad,
            'rightAd'=>$rightAd,
            'targetName'=>$reTarget->name,
			'NavName'  =>$NavName
        ];
        $this->theme->set('now_menu','/article');
        return $this->theme->scope('bre.information',$view)->render();
    }


    /**
     * @param $id
     * @return mixed
     */
    public function newsDetail($id)
    {
        $info = ArticleModel::where('id',$id)->first();
        if(!$info){
            return redirect()->back()->with(['error' => '文章不存在']);
        }
        if(!empty($info['seotitle'])){
            $this->theme->setTitle($info['seotitle']);
            $this->theme->set('keywords',$info['keywords']);
            $this->theme->set('description',$info['description']);
        }else{
            $this->theme->setTitle($info['title'].'|众包威客资讯信息中心_KPPW演示');
            $this->theme->set('keywords',$info['title'].'，资讯中心,KPPW演示');
            $this->theme->set('description','客客专业开源建站系统KPPW演示站点，资讯中心'.$info['title'].'文章详情。');
        }
        $viewTimes = $info['view_times'];
        $keywords = $info['keywords'];
        $catID = $info['cat_id'];
        //点击数
        ArticleModel::where('id',$id)->update(['view_times'=>$viewTimes+1]);
        //上一篇
        $prev = ArticleModel::where('cat_id',$catID)->where('id','<',$id)->orderby('id','desc')->first();
        //下一篇
        $next =  ArticleModel::where('cat_id',$catID)->where('id','>',$id)->first();
        //热门文章
        $reTarget = RePositionModel::where('code','ARTICLEDETAIL_SIDE')->where('is_open','1')->select('id','name')->first();
        if($reTarget->id){
            $recommend = RecommendModel::getRecommendInfo($reTarget->id)->select('*')->get();
            if(count($recommend)){
                $HotList = $recommend;
            }
            else{
                $HotList = [];
            }
        }
        //相关资讯
        $relatedList = ArticleModel::where( 'keywords','like','%'.e($keywords).'%')->orderby('view_times','desc')->limit(3)->get();

        //资讯中心详情顶部广告
        $ad = AdTargetModel::getAdInfo('NEWSINFO_TOP');
        $adTarget = AdTargetModel::where('code','NEWSINFO_TOP')->select('target_id')->first();

        //资讯中心详情右上方广告
        $rightAd = AdTargetModel::getAdInfo('NEWSINFO_RIGHT_TOP');

        $view = [
            'info'=>$info,
            'prev'=>$prev,
            'next'=>$next,
            'hotlist'=>$HotList,
            'relatedList'=> $relatedList,
            'ad'=>$ad,
            'rightAd'=>$rightAd,
            'targetName'=>$adTarget->name
        ];
        return $this->theme->scope('bre.newsDetail',$view)->render();
    }
}

















