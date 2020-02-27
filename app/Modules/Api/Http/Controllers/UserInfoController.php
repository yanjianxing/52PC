<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/7/1
 * Time: 15:24
 */
namespace App\Modules\Api\Http\Controllers;

use App\Http\Requests;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Shop\Models\ShopTagsModel;
use App\Modules\Task\Model\TaskPaySectionModel;
use App\Modules\Task\Model\TaskPayTypeModel;
use App\Modules\Task\Model\TaskRightsModel;
use App\Modules\Task\Model\TaskServiceModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\SkillTagsModel;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiBaseController;
use Validator;
use App\Modules\Task\Model\TaskFocusModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\TagsModel;
use App\Modules\User\Model\UserFocusModel;
use App\Modules\User\Model\UserTagsModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\SuccessCaseModel;
use App\Modules\Im\Model\ImAttentionModel;
use App\Modules\Im\Model\ImMessageModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\Advertisement\Model\AdModel;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Advertisement\Model\RePositionModel;
use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\UserModel;
use App\Modules\Task\Model\TaskAttachmentModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\Task\Model\WorkAttachmentModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\FeedbackModel;
use App\Modules\Manage\Model\ArticleCategoryModel;
use App\Modules\Manage\Model\ArticleModel;
use App\Modules\Order\Model\OrderModel;
use Omnipay;
use Config;
use Illuminate\Support\Facades\Crypt;
use DB;
Use QrCode;
Use Cache;

class UserInfoController extends ApiBaseController
{
    /**
     * 我收藏的任务列表
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function myfocus(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        //查询我收藏的任务
        $query = TaskFocusModel::select('task_focus.id as focus_id', 'tk.*', 'tc.name as category_name', 'ud.avatar')
            ->where('task_focus.uid', $tokenInfo['uid'])
            ->join('task as tk', 'tk.id', '=', 'task_focus.task_id')
            ->leftjoin('cate as tc', 'tc.id', '=', 'tk.cate_id')
            ->leftjoin('user_detail as ud', 'ud.uid', '=', 'tk.uid')
            ->orderBy('task_focus.created_at', 'desc')
            ->paginate()->toArray();

        $task_focus = $query;
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        if (!empty($task_focus['data']) && is_array($task_focus['data'])) {
            foreach ($task_focus['data'] as $k => $v) {
                $task_focus['data'][$k]['avatar'] = $v['avatar'] ? $domain->rule . '/' . $v['avatar'] : $v['avatar'];
                $provinceName = DistrictModel::getDistrictName($v['province']);
                $cityName = DistrictModel::getDistrictName($v['city']);
                $task_focus['data'][$k]['province_name'] = $provinceName;
                $task_focus['data'][$k]['city_name'] = $cityName;
            }
        }
        $status = [
            'status' => [
                0 => '暂不发布',
                1 => '已经发布',
                2 => '赏金托管',
                3 => '审核通过',
                4 => '威客交稿',
                5 => '雇主选稿',
                6 => '任务公示',
                7 => '交付验收',
                8 => '双方互评'
            ]
        ];
        $task_focus['data'] = \CommonClass::intToString($task_focus['data'], $status);
        return $this->formateResponse(1000, 'success', $task_focus);
    }


    /**
     * 删除我收藏的某个任务
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function deleteFocus(Request $request)
    {
        if (!$request->get('id')) {
            return $this->formateResponse(1035, '传送数据错误');
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $result = TaskFocusModel::where('uid', $tokenInfo['uid'])
            ->where('task_id', intval($request->get('id')))->delete();
        if (!$result) {
            return $this->formateResponse(1036, '删除失败');
        }
        return $this->formateResponse(1000, 'success');
    }


    /**
     * 删除我关注的某个人
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function deleteUser(Request $request)
    {
        if (!$request->get('id')) {
            return $this->formateResponse(1037, '传送数据错误');
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $result = UserFocusModel::where('uid', $tokenInfo['uid'])
            ->where('focus_uid', intval($request->get('id')))->delete();
        if (!$result) {
            return $this->formateResponse(1038, '删除失败');
        }
        return $this->formateResponse(1000, 'success');
    }

    /**
     * 收藏任务
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function insertFocusTask(Request $request)
    {
        $data = $request->all();
        $tokenInfo = Crypt::decrypt(urldecode($data['token']));
        $uid = $tokenInfo['uid'];
        if ($uid && $data['task_id']) {
            $arrFocus = array(
                'uid' => $uid,
                'task_id' => $data['task_id'],
                'created_at' => date('Y-m-d H:i:s'),
            );
            $result = TaskFocusModel::create($arrFocus);
            if ($result) {
                return $this->formateResponse(1000, 'success');
            } else {
                return $this->formateResponse(1040, '收藏失败');
            }
        }
    }


    /**
     * 获取行业分类的一级分类信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function skill(Request $request)
    {
        $category_data = TaskCateModel::findByPid([0]);

        if (empty($category_data)) {
            return $this->formateResponse(1039, '暂无信息');
        }
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        foreach ($category_data as $k => $v) {
            $category_data[$k]['pic'] = $category_data[$k]['pic'] ? $domain->rule . '/' . $category_data[$k]['pic'] : $category_data[$k]['pic'];

        }
        //$category_one = json_decode($category_data,true);
        return $this->formateResponse(1000, 'success', $category_data);

    }

    /**
     * 获取行业分类一级分类信息下的子分类信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function secondSkill(Request $request)
    {

        if (!$request->get('id')) {
            return $this->formateResponse(1040, '传送参数不能为空');
        }
        $category_detail = TaskCateModel::findByPid([$request->get('id')]);
        if (empty($category_detail)) {
            return $this->formateResponse(1039, '暂无信息');
        }
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        if ($request->get('token')) {
            $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
            $tags = UserTagsModel::where('uid', $tokenInfo['uid'])->select('tag_id')->get()->toArray();
            if (count($tags)) {
                $tagId = array_flatten($tags);
                $cateId = TagsModel::whereIn('id', $tagId)->select('cate_id')->get()->toArray();

                if (count($cateId)) {
                    $cateId = array_flatten($cateId);
                }
            }
            foreach ($category_detail as $k => $v) {
                if (isset($cateId)) {
                    if (in_array($v['id'], $cateId)) {
                        $category_detail[$k]['isChecked'] = 1;
                    } else {
                        $category_detail[$k]['isChecked'] = 0;
                    }
                } else {
                    $category_detail[$k]['isChecked'] = 0;
                }
                $category_detail[$k]['pic'] = $category_detail[$k]['pic'] ? $domain->rule . '/' . $category_detail[$k]['pic'] : $category_detail[$k]['pic'];

            }
        } else {
            foreach ($category_detail as $k => $v) {
                $category_detail[$k]['pic'] = $category_detail[$k]['pic'] ? $domain->rule . '/' . $category_detail[$k]['pic'] : $category_detail[$k]['pic'];

            }
        }

        return $this->formateResponse(1000, 'success', $category_detail);

    }

    /**
     * 所有技能标签(是否选择)
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getAllSkill(Request $request)
    {
        if ($request->get('token')) {
            $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
            $tags = UserTagsModel::where('uid', $tokenInfo['uid'])->select('tag_id')->get()->toArray();
            if (count($tags)) {
                $tagId = array_flatten($tags);
                $cateId = TagsModel::whereIn('id', $tagId)->select('cate_id')->get()->toArray();

                if (count($cateId)) {
                    $cateId = array_flatten($cateId);
                }
            }
        }elseif($request->get('shop_id')){
            $tags = ShopTagsModel::where('shop_id', $request->get('shop_id'))->select('tag_id')->get()->toArray();
            if (count($tags)) {
                $tagId = array_flatten($tags);
                $cateId = TagsModel::whereIn('id', $tagId)->select('cate_id')->get()->toArray();

                if (count($cateId)) {
                    $cateId = array_flatten($cateId);
                }
            }
        }
        //dd($cateId);
        $category_data = TaskCateModel::findByPid([0]);
        if(!empty($category_data)){
            foreach($category_data as $key => $val){
                $category_detail = TaskCateModel::findByPid([$val['id']]);
                if(!empty($category_data)){
                    $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
                    if (isset($cateId)) {
                        foreach ($category_detail as $k => $v) {
                            $category_detail[$k]['isChecked'] = 0;
                                if (in_array($v['id'], $cateId)) {
                                    $category_detail[$k]['isChecked'] = 1;
                                }
                            $category_detail[$k]['pic'] = $category_detail[$k]['pic'] ? $domain->rule . '/' . $category_detail[$k]['pic'] : $category_detail[$k]['pic'];

                        }
                    } else {
                        foreach ($category_detail as $k => $v) {
                            $category_detail[$k]['isChecked'] = 0;
                            $category_detail[$k]['pic'] = $category_detail[$k]['pic'] ? $domain->rule . '/' . $category_detail[$k]['pic'] : $category_detail[$k]['pic'];

                        }
                    }
                    $category_data[$key]['second_cate'] = $category_detail;
                }else{
                    $category_data[$key]['second_cate'] = [];
                }
            }
        }

        return $this->formateResponse(1000, 'success', $category_data);
    }

    /**
     * 用户技能标签存储
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function skillSave(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        if ($request->get('id')) {
            $num = UserTagsModel::where('uid', $tokenInfo['uid'])->count();
            if ($num >= 3) {
                return $this->formateResponse(1042, '标签数量不能超过3个');
            }
            $tagInfo = TagsModel::where('cate_id', $request->get('id'))->select('id')->first();
            if (!isset($tagInfo)) {
                return $this->formateResponse(1043, '该标签不存在');
            }
            $addInfo = [
                'tag_id' => $tagInfo->id,
                'uid' => $tokenInfo['uid']
            ];
            $res = UserTagsModel::create($addInfo);

            if (!isset($res)) {
                return $this->formateResponse(1008, '标签添加失败');
            }

            //查询我的标签
            $userSkill = UserTagsModel::where('uid', $tokenInfo['uid'])->select('tag_id')->get()->toArray();
            $userSkill = array_flatten($userSkill);
            $skill = SkillTagsModel::whereIn('id',$userSkill)->select('tag_name')->get()->toArray();
            $skill = array_flatten($skill);
            return $this->formateResponse(1000, 'success',$skill);
        }
        if ($request->get('cancel_id')) {
            $tagInfo = TagsModel::where('skill_tags.cate_id', $request->get('cancel_id'))
                ->where('tag_user.uid', $tokenInfo['uid'])
                ->leftjoin('tag_user', 'skill_tags.id', '=', 'tag_user.tag_id')
                ->select('tag_user.tag_id')
                ->first();
            if (!isset($tagInfo)) {
                return $this->formateResponse(1043, '传送参数错误');
            }
            $res = UserTagsModel::where('tag_id', $tagInfo->tag_id)->where('uid', $tokenInfo['uid'])->delete();
            if (!isset($res)) {
                return $this->formateResponse(1008, '标签删除失败');
            }
            //查询我的标签
            $userSkill = UserTagsModel::where('uid', $tokenInfo['uid'])->select('tag_id')->get()->toArray();
            $userSkill = array_flatten($userSkill);
            $skill = SkillTagsModel::whereIn('id',$userSkill)->select('tag_name')->get()->toArray();
            $skill = array_flatten($skill);
            return $this->formateResponse(1000, 'success',$skill);
        }
    }


    /**
     * 用户个人案例信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function personCase(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $query = SuccessCaseModel::select('success_case.id', 'success_case.title', 'success_case.pic');
        $list = $query->leftJoin('cate as tc', 'success_case.cate_id', '=', 'tc.id')
            ->leftjoin('user_detail as ud', 'ud.uid', '=', 'success_case.uid')->where('ud.uid', $tokenInfo['uid'])
            ->orderBy('success_case.created_at', 'desc')
            ->paginate(8)->toArray();
        if ($list['total']) {
            $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['pic'] = $v['pic'] ? $domain->rule . '/' . $v['pic'] : $v['pic'];
            }
        }
        return $this->formateResponse(1000, 'success', $list);
    }


    /**
     * 添加个人案例信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function addCase(Request $request)
    {

        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $file = $request->file('pic');
        if (!$request->get('cate_id') or !$file or !$request->get('title')) {
            return $this->formateResponse(1045, '传送数据不能为空');
        }

        $result = \FileClass::uploadFile($file, 'sys');
        $result = json_decode($result, true);
        $data = array(
            'pic' => $result['data']['url'],
            'uid' => $tokenInfo['uid'],
            'title' => $request->get('title'),
            'desc' => e($request->get('desc')),
            'cate_id' => $request->get('cate_id'),
            'created_at' => date('Y-m-d H:i:s', time()),
        );
        $result2 = SuccessCaseModel::create($data);

        if (!$result2) {
            return $this->formateResponse(1046, '成功案例添加失败');
        }
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        $result2->pic = $result2->pic ? $domain->rule . '/' . $result2->pic : $result2->pic;
        return $this->formateResponse(1000, 'success', $result2);
    }


    /**
     * 获取个人案例信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function caseInfo(Request $request)
    {
        if (!$request->get('id')) {
            return $this->formateResponse(1047, '传送参数不能为空');
        }
        $successCaseInfo = SuccessCaseModel::find(intval($request->get('id')));
        if (empty($successCaseInfo)) {
            return $this->formateResponse(1048, '传送数据有误');
        }
        $successCaseInfo->desc = htmlspecialchars_decode($successCaseInfo->desc);
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        $successCaseInfo->pic = $successCaseInfo->pic ? $domain->rule . '/' . $successCaseInfo->pic : $successCaseInfo->pic;
        return $this->formateResponse(1000, 'success', $successCaseInfo);
    }


    /**
     * 修改个人案例信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function caseUpdate(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $file = $request->file('pic');
        if (!$request->get('cate_id') or !$file or !$request->get('title') or !$request->get('id')) {
            return $this->formateResponse(1045, '传送数据不能为空');
        }

        $result = \FileClass::uploadFile($file, 'sys');
        $result = json_decode($result, true);
        $data = array(
            'pic' => $result['data']['url'],
            'uid' => $tokenInfo['uid'],
            'title' => $request->get('title'),
            'desc' => e($request->get('desc')),
            'cate_id' => $request->get('cate_id'),
            'created_at' => date('Y-m-d H:i:s', time()),
        );
        $result2 = SuccessCaseModel::where('id', intval($request->get('id')))->update($data);

        if (!$result2) {
            return $this->formateResponse(1046, '成功案例修改失败');
        }

        return $this->formateResponse(1000, 'success');
    }


    /**
     * 获取临时消息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function myTalk(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $myTalker = ImAttentionModel::where('im_attention.uid', $tokenInfo['uid'])
            ->where('im_attention.friend_uid', '<>', $tokenInfo['uid'])
            ->leftjoin('users', 'im_attention.friend_uid', '=', 'users.id')
            ->leftjoin('user_detail', 'users.id', '=', 'user_detail.uid');
        if ($request->get('nickname')) {
            $myTalker = $myTalker->where('users.name', 'like', '%' . $request->get('nickname') . '%');
        }
        $myTalker = $myTalker->select('im_attention.friend_uid', 'users.name as nickname', 'user_detail.avatar')
            ->groupBy('im_attention.friend_uid')->get();
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();

        if (!empty($myTalker)) {
            foreach ($myTalker as $k => $v) {
                $v->avatar = $v->avatar ? $domain->rule . '/' . $v->avatar : $v->avatar;
                $num = ImMessageModel::where('to_uid', $tokenInfo['uid'])
                    ->where('from_uid', $v->friend_uid)
                    ->where('status', 1)
                    ->count();
                $v->num = $num;
                $myTalker[$k] = $v;
            }
        }
        return $this->formateResponse(1000, 'success', $myTalker);
    }


    /**
     * 获取我的关注
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function myAttention(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $myAttention = UserFocusModel::where('user_focus.uid', $tokenInfo['uid'])
            ->where('user_focus.focus_uid', '<>', $tokenInfo['uid'])
            ->leftjoin('user_detail', 'user_focus.focus_uid', '=', 'user_detail.uid')
            ->leftjoin('users', 'user_detail.uid', '=', 'users.id');
        if ($request->get('nickname')) {
            $myAttention = $myAttention->where('users.name', 'like', '%' . $request->get('nickname') . '%');
        }
        $myAttention = $myAttention->select('user_focus.focus_uid', 'users.name as nickname', 'user_detail.avatar')
            ->groupBy('user_focus.focus_uid')->paginate()->toArray();
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        if ($myAttention['total']) {
            foreach ($myAttention['data'] as $k => $v) {
                $v['avatar'] = $v['avatar'] ? $domain->rule . '/' . $v['avatar'] : $v['avatar'];
                $num = ImMessageModel::where('to_uid', $tokenInfo['uid'])
                    ->where('status', 1)
                    ->where('from_uid', $v['focus_uid'])
                    ->count();
                $v['num'] = $num;
                $myAttention['data'][$k] = $v;
            }
        }
        return $this->formateResponse(1000, 'success', $myAttention);
    }

    /**
     * 加关注
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function addAttention(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        if (!$request->get('focus_uid')) {
            return $this->formateResponse(1047, '传送数据不能为空');
        }
        $focusInfo = [
            'uid' => $tokenInfo['uid'],
            'focus_uid' => $request->get('focus_uid'),
            'created_at' => date('Y-m-d H:i:s')
        ];
        $info = UserFocusModel::where('uid', $tokenInfo['uid'])->where('focus_uid', $request->get('focus_uid'))->first();
        if (empty($info)) {
            $useFocusInfo = UserFocusModel::create($focusInfo);

            //成为IM好友
            $res = ImAttentionModel::where(['uid' => $tokenInfo['uid'], 'friend_uid' => $request->get('focus_uid')])->first();
            if (empty($res)) {
                ImAttentionModel::insert([
                    [
                        'uid' => $tokenInfo['uid'],
                        'friend_uid' => $request->get('focus_uid')
                    ],
                    [
                        'uid' => $request->get('focus_uid'),
                        'friend_uid' => $tokenInfo['uid']
                    ]

                ]);
            }
            if (empty($useFocusInfo)) {
                return $this->formateResponse(1048, '加关注失败');
            } else {
                $useFocus = UserFocusModel::find($useFocusInfo->id);
                return $this->formateResponse(1000, 'success', $useFocus);
            }
        } else {
            $useFocus = UserFocusModel::find($info->id);
            return $this->formateResponse(1000, 'success', $useFocus);
        }

    }


    /**
     * 提交聊天信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function addMessage(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        if (!$request->get('to_uid') or !$request->get('content')) {
            return $this->formateResponse(1047, '传送数据不能为空');
        }
        $focusInfo = [
            'from_uid' => $tokenInfo['uid'],
            'to_uid' => $request->get('focus_uid'),
            'content' => $request->get('content'),
            'created_at' => date('Y-m-d H:i:s')
        ];
        $useFocusInfo = ImMessageModel::create($focusInfo);
        if (empty($useFocusInfo)) {
            return $this->formateResponse(1048, '创建消息失败');
        }
        $useFocus = ImMessageModel::find($useFocusInfo->id);
        return $this->formateResponse(1000, 'success', $useFocus);
    }


    /**
     * 将未读消息修改为已读
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function updateMessStatus(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        if (!$request->get('from_uid')) {
            return $this->formateResponse(1047, '传送数据不能为空');
        }
        $messageInfo = ImMessageModel::where('from_uid', $request->get('from_uid'))->where('to_uid', $tokenInfo['uid'])->get();
        if (empty($messageInfo)) {
            return $this->formateResponse(1048, '传送数据错误');
        }
        foreach ($messageInfo as $k => $v) {
            ImMessageModel::find($v->id)->update(['status' => 2]);
        }
        return $this->formateResponse(1000, 'success');
    }


    /**
     * 删除好友
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function deleteTalk(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $friend_uid = $request->get('friend_uid');
        if (is_int($friend_uid) && $friend_uid > 0) {
            return $this->formateResponse(1049, '用户ID类型错误');
        }
        $talkInfo = ImAttentionModel::where('uid', $tokenInfo['uid'])->where('friend_uid', $friend_uid)->get()->toArray();
        if (!isset($talkInfo[0])) {
            return $this->formateResponse(1050, '需要删除的好友不存在');
        }
        $res = ImAttentionModel::where('id', $talkInfo[0]['id'])->delete();
        if ($res) {
            return $this->formateResponse(1000, '删除好友成功');
        }
        return $this->formateResponse(1051, '删除好友失败');
    }


    /**
     * 获取我是雇主首页顶部幻灯片信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function slideInfo(Request $request)
    {
        $adTargetInfo = AdTargetModel::where('code', 'APP_TOP_SLIDE')->select('target_id')->first()->toArray();
        if (count($adTargetInfo)) {
            $adInfo = AdModel::where('target_id', $adTargetInfo['target_id'])
                ->where('is_open', '1')
                ->where(function ($adInfo) {
                    $adInfo->where('end_time', '0000-00-00 00:00:00')
                        ->orWhere('end_time', '>', date('Y-m-d h:i:s', time()));
                })
                ->select('*')
                ->get()
                ->toArray();
            $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
            foreach ($adInfo as $k => $v) {
                $adInfo[$k]['ad_file'] = $v['ad_file'] ? $domain->rule . '/' . $v['ad_file'] : $v['ad_file'];
            }
            return $this->formateResponse(1000, '获取广告幻灯片信息成功', $adInfo);

        }
        return $this->formateResponse(1052, '暂无广告位信息');
    }


    /**
     * 获取首页热门服务信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function hotService(Request $request)
    {
        $reTarget = RePositionModel::where('code', 'HOME_MIDDLE')->where('is_open', '1')->select('id', 'name')->first();
        if ($reTarget->id) {
            $recommend = RecommendModel::where('position_id', $reTarget->id)
                ->where('is_open', 1)
                ->where(function ($recommend) {
                    $recommend->where('end_time', '0000-00-00 00:00:00')
                        ->orWhere('end_time', '>', date('Y-m-d h:i:s', time()));
                })
                ->select('*')
                ->get()
                ->toArray();

            if (count($recommend)) {
                $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
                foreach ($recommend as $k => $v) {
                    $v['recommend_pic'] = $v['recommend_pic'] ? $domain->rule . '/' . $v['recommend_pic'] : $v['recommend_pic'];
                    $tag_ids = UserTagsModel::where('uid', $v['recommend_id'])->select('tag_id')->get()->toArray();
                    if (count($tag_ids)) {
                        $tags = TagsModel::whereIn('id', $tag_ids)->select('tag_name')->get()->toArray();
                        if (count($tags)) {
                            $v['tags'] = $tags;
                        } else {
                            $v['tags'] = [];
                        }
                    } else {
                        $v['tags'] = [];
                    }

                    $comment = CommentModel::where('to_uid', $v['recommend_id'])->count();
                    $goodComment = CommentModel::where('to_uid', $v['recommend_id'])->where('type', 1)->count();
                    if ($comment) {
                        $v['percent'] = number_format($goodComment / $comment, 3) * 100;
                    } else {
                        $v['percent'] = 100;
                    }
                    $recommend[$k] = $v;
                }
            }
            return $this->formateResponse(1000, '获取热门服务信息成功', $recommend);

        } else {
            return $this->formateResponse(1053, '暂无热门服务信息');
        }

    }

    /**
     * 首页推荐热门店铺 服务 作品
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function hotShop(Request $request)
    {
        if($request->get('type')){
            $type = $request->get('type');
        }else{
            $type = 1;
        }
        $typeCode = '';
        switch($type){
            case 1://推荐店铺
                $reTarget = RePositionModel::where('code', 'HOME_MIDDLE_SHOP')->where('is_open', '1')->select('id', 'name')->first();
                if ($reTarget->id) {
                    $recommend = RecommendModel::getRecommendInfo($reTarget['id'], 'shop')
                        ->where('shop.status', 1)
                        ->leftJoin('shop', 'shop.id', '=', 'recommend.recommend_id')
                        ->select('shop.id', 'shop.uid', 'shop.shop_pic',
                            'shop.shop_name', 'shop.total_comment', 'shop.good_comment')
                        ->orderBy('recommend.created_at', 'DESC')
                        ->get()->toArray();
                    if (count($recommend)) {
                        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
                        foreach ($recommend as $k => $v) {
                            $v['shop_pic'] = $v['shop_pic'] ? $domain->rule . '/' . $v['shop_pic'] : $domain->rule . '/' . $v['shop_pic'];
                            $tag_ids = ShopTagsModel::where('shop_id', $v['id'])->select('tag_id')->get()->toArray();
                            if (count($tag_ids)) {
                                $tags = TagsModel::whereIn('id', $tag_ids)->select('tag_name')->get()->toArray();
                                if (count($tags)) {
                                    $v['tags'] = array_flatten($tags);
                                } else {
                                    $v['tags'] = [];
                                }
                            } else {
                                $v['tags'] = [];
                            }
                            $shop_ids[] = $v['id'];
                            if ($v['total_comment']) {
                                $v['percent'] = number_format($v['good_comment'] / $v['total_comment'], 3) * 100;
                            } else {
                                $v['percent'] = 100;
                            }
                            $recommend[$k] = $v;
                        }
                        //店铺类型
                        $uidArr = array_pluck($recommend,'uid');
                        /*$companyInfo = EnterpriseAuthModel::whereIn('uid', $uidArr)->where('status',1)
                            ->orderBy('created_at', 'desc')->get()->toArray();
                        if(!empty($companyInfo)){
                            $enterpriseIds = array_unique(array_pluck($companyInfo,'uid'));
                        }else{
                            $enterpriseIds = [];
                        }*/
                        if (!empty($shop_ids)) {
                            $provinceInfo = ShopModel::join('district', 'shop.province', '=', 'district.id')
                                ->select('shop.id', 'district.name')
                                ->whereIn('shop.id', $shop_ids)
                                ->where('shop.status', 1)
                                ->get()->toArray();
                            $cityInfo = ShopModel::join('district', 'shop.city', '=', 'district.id')
                                ->select('shop.id', 'district.name')
                                ->whereIn('shop.id', $shop_ids)
                                ->where('shop.status', 1)
                                ->get()->toArray();
                            $provinceInfo = collect($provinceInfo)->pluck('name', 'id')->all();
                            $cityInfo = collect($cityInfo)->pluck('name', 'id')->all();
                            //dd($provinceInfo,$cityInfo);
                            //dd($recommend);
                            foreach ($recommend as $k => $v) {
                                $province = (isset($provinceInfo[$v['id']])) ? $provinceInfo[$v['id']] : null;
                                $city = (isset($cityInfo[$v['id']])) ? $cityInfo[$v['id']] : null;
                                $recommend[$k]['city_name'] = $province.$city;
                                /*if(in_array($v['uid'],$enterpriseIds)){
                                    $recommend[$k]['isEnterprise'] = 1;
                                }else{
                                    $recommend[$k]['isEnterprise'] = 0;
                                }*/
                            }

                        }

                        if(!empty($uidArr)){
                            //查询店铺和店铺所属用户的绑定关系
                            $userAuthOne = AuthRecordModel::whereIn('uid', $uidArr)->where('status', 2)
                                ->whereIn('auth_code',['bank','alipay'])->get()->toArray();
                            $userAuthTwo = AuthRecordModel::whereIn('uid', $uidArr)->where('status', 1)
                                ->whereIn('auth_code',['realname','enterprise'])->get()->toArray();
                            $emailAuth = UserModel::whereIn('id',$uidArr)->where('email_status', 2)
                                ->select('id as uid','email_status')->get()->toArray();
                            $userAuth = array_merge($userAuthOne,$userAuthTwo,$emailAuth);
                        }else{
                            $userAuth = array();
                        }
                        $newUserAuth = array_reduce($userAuth,function(&$newUserAuth,$v){
                            if(isset($v['email_status'])){
                                $newUserAuth[$v['uid']][] = 'email';
                            }else{
                                $newUserAuth[$v['uid']][] = $v['auth_code'];
                            }
                            return $newUserAuth;
                        });
                        if(!empty($newUserAuth)){
                            foreach($recommend as $k => $v){
                                foreach($newUserAuth as $k1 => $v1){
                                    if($v['uid'] == $k1){
                                        if(in_array('enterprise',$v1)){
                                            $recommend[$k]['isEnterprise'] = 1;
                                        }else{
                                            $recommend[$k]['isEnterprise'] = 0;
                                        }
                                        if(in_array('bank',$v1)){
                                            $recommend[$k]['bank'] = 1;
                                        }else{
                                            $recommend[$k]['bank'] = 0;
                                        }
                                        if(in_array('alipay',$v1)){
                                            $recommend[$k]['alipay'] = 1;
                                        }else{
                                            $recommend[$k]['alipay'] = 0;
                                        }
                                        if(in_array('email',$v1)){
                                            $recommend[$k]['email'] = 1;
                                        }else{
                                            $recommend[$k]['email'] = 0;
                                        }
                                        if(in_array('realname',$v1)){
                                            $recommend[$k]['realname'] = 1;
                                        }else{
                                            $recommend[$k]['realname'] = 0;
                                        }
                                    }
                                }
                            }
                        }

                    }
                    return $this->formateResponse(1000, '获取信息成功', $recommend);

                } else {
                    return $this->formateResponse(1053, '暂无信息');
                }
                break;
            case 2:// 推荐作品
                $reTarget = RePositionModel::where('code','HOME_MIDDLE_WORK')->where('is_open',1)->first();
                $typeCode = 'work';

                break;
            case 3://推荐服务
                $reTarget = RePositionModel::where('code','HOME_MIDDLE_SERVICE')->where('is_open',1)->first();
                $typeCode = 'server';
                break;
        }
        if(isset($reTarget['id']) && in_array($typeCode,['work','server'])){
            $recommendWork = RecommendModel::getRecommendInfo($reTarget['id'],$typeCode)
                ->join('goods','goods.id','=','recommend.recommend_id')
                ->where('goods.status',1)//上架中
                ->where('goods.is_delete',0)//未删除
                ->select('goods.id','goods.uid','goods.shop_id','goods.title','goods.cover','goods.cash','goods.unit','goods.sales_num','goods.comments_num','goods.good_comment')
                ->orderBy('recommend.sort','ASC')
                ->orderBy('recommend.created_at','DESC')
                ->get()->toArray();
            $uidArr = array_pluck($recommendWork,'uid');
            $userArr = UserModel::whereIn('id',$uidArr)->select('id','name')->get()->toArray();
            $userArr = array_reduce($userArr,function(&$userArr,$v){
                $userArr[$v['id']] = $v['name'];
                return $userArr;
            });
            if(!empty($recommendWork)){
                $domain = \CommonClass::getDomain();
                foreach($recommendWork as $k => $v){
                    if($v['cover']){
                        $recommendWork[$k]['cover'] = $domain.'/'.$v['cover'];
                    }
                    $recommendWork[$k]['comments_num'] = $v['comments_num'] <= 0 ? 0 : $v['comments_num'];
                    $recommendWork[$k]['good_comment'] = $v['good_comment'] <= 0 ? 0 : $v['good_comment'];
                    if ($v['comments_num']) {
                        $recommendWork[$k]['percent'] = number_format($v['good_comment'] / $v['comments_num'], 3) * 100;
                    } else {
                        $recommendWork[$k]['percent'] = 100;
                    }
                    switch($v['unit']){
                        case 0:
                            $recommendWork[$k]['unit'] = '件';
                            break;
                        case 1:
                            $recommendWork[$k]['unit'] = '时';
                            break;
                        case 2:
                            $recommendWork[$k]['unit'] = '份';
                            break;
                        case 3:
                            $recommendWork[$k]['unit'] = '个';
                            break;
                        case 4:
                            $recommendWork[$k]['unit'] = '张';
                            break;
                        case 5:
                            $recommendWork[$k]['unit'] = '套';
                            break;
                        default:
                            $recommendWork[$k]['unit'] = '件';
                    }
                    $recommendWork[$k]['username'] = '';
                    if(isset($userArr) && in_array($v['uid'],array_keys($userArr))){
                        $recommendWork[$k]['username'] = $userArr[$v['uid']];
                    }
                }
            }
            return $this->formateResponse(1000, '获取信息成功', $recommendWork);
        }else{
            return $this->formateResponse(1053, '暂无信息');
        }

    }


    /**
     * 获取分类下的热门服务商信息
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function serviceByCate(Request $request)
    {
        $cate_id = intval($request->get('cate_id'));
        if (!$cate_id) {
            return $this->formateResponse('1054', '传送数据不能为空');
        }
        $tagInfo = $cateKey = $cateValue = $userKey = $userValue = $serverInfo = [];
        $cateInfo = TaskCateModel::where('pid', $cate_id)->select('id')->get()->toArray();
        if (isset($cateInfo)) {
            $cateInfo = array_flatten($cateInfo);
            $tagInfo = TagsModel::whereIn('cate_id', $cateInfo)->select('id', 'tag_name')->get()->toArray();
        }
        if (count($tagInfo)) {
            foreach ($tagInfo as $k => $v) {
                $cateKey[$k] = $v['id'];
                $cateValue[$v['id']] = $v['tag_name'];
            }
        }
        $userTagRelation = UserTagsModel::whereIn('tag_id', $cateKey)->select('tag_id', 'uid')->get()->toArray();
        if (count($userTagRelation)) {
            foreach ($userTagRelation as $key => $value) {
                $userKey[$key] = $value['uid'];
                $userValue[$value['uid']] = $value['tag_id'];
            }
        }
        $userInfo = UserModel::whereIn('users.id', $userKey)
            ->where('users.status', '<>', 2)
            ->leftjoin('user_detail', 'users.id', '=', 'user_detail.uid')
            ->select('users.id', 'users.name', 'user_detail.avatar')
            ->orderBy('user_detail.receive_task_num','desc')->orderBy('user_detail.employee_praise_rate','desc')
            ->limit(8)->get()->toArray();
        if ($userInfo) {
            $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
            foreach ($userInfo as $uKey => $uValue) {
                $userInfo[$uKey]['avatar'] = $userInfo[$uKey]['avatar'] ? $domain->rule . '/' . $userInfo[$uKey]['avatar'] : $userInfo[$uKey]['avatar'];
                //$userInfo[$uKey]['tags_id'] = $userValue[$uValue['id']];
                $userInfo[$uKey]['tags'][] = $cateValue[$userValue[$uValue['id']]];  //服务商标签
                $comment = CommentModel::where('to_uid', $uValue['id'])->count();
                $goodComment = CommentModel::where('to_uid', $uValue['id'])->where('type', 1)->count();
                if ($comment) {
                    $userInfo[$uKey]['percent'] = number_format($goodComment / $comment, 3) * 100;
                } else {
                    $userInfo[$uKey]['percent'] = 100;
                }
            }
            $serverInfo = $userInfo;
        }
        return $this->formateResponse(1000, '获取类型下的服务商信息成功', $serverInfo);
    }


    /**
     * 获取任务分类，父级分类及其对应的子分类
     * @return \Illuminate\Http\Response
     */
    public function taskCate()
    {
        $parentCate = TaskCateModel::findAll();
        return $this->formateResponse(1000, 'success', $parentCate);
    }

    /**
     * 获取热门任务分类
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function hotCate(Request $request)
    {
        $num = $request->get('limit') ? $request->get('limit') : 6;
        $hotCate = TaskCateModel::hotCate($num);
        return $this->formateResponse(1000, 'success', $hotCate);
    }

    /**
     * 根据任务id查询任务详情信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function showTaskDetail(Request $request)
    {
        $id = intval($request->get('id'));
        $task = TaskModel::findById($id);
        if (!$task) {
            return $this->formateResponse(2001, '未找到与之对应ID的任务信息');
        }
        $task->update(['view_count' => $task->view_count + 1]);
        $task->desc = htmlspecialchars_decode($task->desc);
        //中标数量
        $task->bidnum = WorkModel::where('task_id', $id)->where('status', '1')->count();
        /*if ($task->status == 3 && strtotime($task->begin_at) < time()) {
            $task->status = 4;//威客投稿状态
        }*/

        if($task->bounty_status == 1){
            $task->bounty_status_desc = '已托管';
        }else{
            if($task->task_type == 'zhaobiao'){
                $task->bounty = '可议价';
            }
            $task->bounty_status_desc = '未托管';
        }
        $task->created_at = date('Y-m-d',strtotime($task['created_at']));
        $task->focused = 0;//是否收藏
        $role = 'visitor';
        $isToWork = 0;//没有投稿
        $isDelivery = 0;//没有交付稿件
        $isCommentEmployer = 0;//是否评价雇主
        $isCommentEmployee = 0;//是否评价威客
        $uid = 0;
        if ($request->get('token')) {
            $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
            $uid = $tokenInfo['uid'];
            //判断是否是雇主
            if($task->uid == $uid){
                $role = 'employer';//雇主
                if($task->status >= 8){
                    $comment = CommentModel::where('task_id',$id)->where('from_uid',$uid)->count();
                    $isCommentEmployee = $comment;
                }
            }else{
                $role = 'employee';
                $work = WorkModel::where('task_id', $id)->where('uid', $uid)->first();
                if($work && $work->status == 0){
                    $isToWork = 1;//已经投稿
                }elseif($work && $work->status == 1){
                    $isToWork = 2;//已经中标
                    //查询是否有交付稿件
                    $delivery = WorkModel::where('task_id', $id)->whereIn('status',[2,3,4])->where('uid', $uid)->orderBy('id','desc')->first();
                    if($delivery && $delivery->status == 2){
                        $isDelivery = 1;//等待验收
                    }elseif($delivery && $delivery->status == 3){
                        $isDelivery = 2;//验收通过
                        if($task->status >= 8){
                            $comment = CommentModel::where('task_id',$id)->where('to_uid',$task->uid)->where('from_uid',$uid)->first();
                            if($comment){
                                $isCommentEmployer = 1;
                            }
                        }
                    } elseif($delivery && $delivery->status == 4){
                        $isDelivery = 3;//维权中
                    }
                }
                /*if($isToWork == 0 && $task->status > 5){
                    $role = 'visitor';
                }*/
            }
            //是否收藏任务
            $focusTask = TaskFocusModel::where('uid', $tokenInfo['uid'])->where('task_id', $id)->first();
            if (isset($focusTask)) {
                $task->focused = 1;
            }
        }

        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        $userInfo = UserModel::select('users.name')->where('users.id', $task->uid)->first();
        //雇主用户名
        $task->name = '';
        if(isset($userInfo)&&isset($userInfo->name)){
            $task->name = $userInfo->name;
        }

        //获取任务附件
        $attachment = [];
        $arrAttachmentIDs = TaskAttachmentModel::findByTid($id);
        if (count($arrAttachmentIDs)) {
            $attachment = AttachmentModel::findByIds($arrAttachmentIDs);
            if (isset($attachment)) {
                foreach ($attachment as $k => $v) {
                    $attachment[$k]['url'] = $attachment[$k]['url'] ? $domain->rule . '/' . $attachment[$k]['url'] : $attachment[$k]['url'];
                }
            }
        }
        $task->attachment = $attachment;

        //判断是否限制地域
        $task->city_name = '';
        if($task->region_limit == 1){
            //查询省
            $task->city_name =  DistrictModel::getAreaName($task->province,$task->city);
        }

        //用户角色
        $task->status_desc = '';
        $task->time_desc = '';
        $task->role = $role;
        $task->employee_button = 0;//0:未登录不显示按钮 1:我要投稿 2:我要投稿(灰色 错过投稿期) 3:等待选稿 4:公示中 5:未中标(灰色) 6:等待确认付款方式 7:我要交稿 8:等待验收 9:验收通过 10:我要评价 11:我要评价(灰色) 12:维权中等待后台审核 13:已结束 14:等待托管赏金
        $task->employee_button_desc = '';
        $task->employer_button = 0;//1:等待投稿 2:我要选稿 3:公示中 4:托管赏金 5:等待确认付款方式 6:等待交付 7:我要验收(维权)8:我要评价 9:维权中等待后台审核 10:已结束 11:我要评价(灰色)
        $task->employer_button_desc = '';
        $task->task_service = [];//购买的增值服务
        $task->delivery_sort = 0;// 招标任务交付稿件阶段
        $task->delivery_work_id = 0;//  招标任务最后验收通过的交付稿件id
        $task->to_comment_name = '';
        $task->to_comment_avatar = '';
        switch($task->task_type){
            case 'xuanshang'://悬赏
                $order = OrderModel::where('task_id',$id)->where('status',1)->get()->toArray();

                switch($task->status){
                    case 3:
                        $task->status_desc = '审核通过';
                        if( strtotime($task->begin_at) < time() && time() < strtotime($task->delivery_deadline)){
                            $task->status_desc = '投稿中';
                            $time = \CommonClass::timediff(time(),strtotime($task->delivery_deadline));
                            $task->time_desc = '距投稿结束剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';
                            switch($role){
                                case 'employee':
                                    $task->employee_button = 1;
                                    $task->employee_button_desc = '我要投稿';
                                    break;
                                case 'employer':
                                    $task->employer_button = 1;
                                    $task->employer_button_desc = '等待投稿';
                                    break;
                            }
                        }else{
                            $time = \CommonClass::timediff(time(),strtotime($task->begin_at));
                            $task->time_desc = '距投稿开始剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';

                        }
                        break;
                    case 4:
                        $task->status_desc = '投稿中';
                        if(time() < strtotime($task->delivery_deadline)){
                            $time = \CommonClass::timediff(time(),strtotime($task->delivery_deadline));
                            $task->time_desc = '距投稿结束剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';
                        }
                        switch($role){
                            case 'employee':
                                if($isToWork == 0){
                                    $task->employee_button = 1;
                                    $task->employee_button_desc = '我要投稿';
                                }elseif($isToWork == 1){
                                    $task->employee_button = 3;
                                    $task->employee_button_desc = '等待选稿';
                                }
                                break;
                            case 'employer':
                                $task->employer_button = 1;
                                $task->employer_button_desc = '等待投稿';
                                break;
                        }

                        break;
                    case 5:
                        $task->status_desc = '选稿中';
                        $task->time_desc = '';
                        $task_select_work = \CommonClass::getConfig('task_select_work');
                        $task_select_work = strtotime($task->selected_work_at) + $task_select_work*24*3600;
                        if(time() < $task_select_work){
                            $time = \CommonClass::timediff(time(),$task_select_work);
                            if($task->task_type == 'xuanshang'){
                                $task->time_desc = '距选稿结束剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';
                            }

                        }
                        switch($role){
                            case 'employee':
                                if($isToWork == 0){
                                    if($task->bidnum = $task->worker_num){//中标人数已满
                                        $task->employee_button = 2;
                                    }else{
                                        $task->employee_button = 1;
                                    }
                                    $task->employee_button_desc = '我要投稿';
                                }elseif($isToWork == 1){
                                    $task->employee_button = 3;
                                    $task->employee_button_desc = '等待选稿';
                                }elseif($isToWork == 2){
                                    $task->employee_button = 4;//等待其他人中标开始工作
                                    $task->employee_button_desc = '等待选稿';
                                }
                                break;
                            case 'employer':
                                $task->employer_button = 2;
                                $task->employer_button_desc = '我要选稿';
                                break;
                        }

                        break;
                    case 6:
                        $task->status_desc = '公示中';
                        $task->time_desc = '';
                        $days = \CommonClass::getConfig('task_publicity_day');
                        $task_publicity_day = strtotime($task['publicity_at']) + $days*24*3600;
                        if(time() < $task_publicity_day){
                            $time = \CommonClass::timediff(time(),$task_publicity_day);
                            $task->time_desc = '距公示结束剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';
                        }
                        switch($role){
                            case 'employee':
                                $task->employee_button = 4;
                                $task->employee_button_desc = '公示中';
                                break;
                            case 'employer':
                                $task->employer_button = 3;
                                $task->employer_button_desc = '公示中';
                                break;
                        }
                        break;
                    case 7:
                        $task->status_desc = '工作中';
                        $task->time_desc = '';
                        $task_delivery_max_time = \CommonClass::getConfig('task_delivery_max_time');
                        $task_check_time_limit = \CommonClass::getConfig('task_check_time_limit');
                        $task_max_delivery = strtotime($task->checked_at) + ($task_delivery_max_time+$task_check_time_limit)*24*3600;
                        if(time() < $task_max_delivery){
                            $time = \CommonClass::timediff(time(),$task_max_delivery);
                            $task->time_desc = '距交付结束剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';
                        }
                        switch($role){
                            case 'employee':
                                if($isToWork == 2){
                                    if($isDelivery == 0){
                                        $task->employee_button = 7;
                                        $task->employee_button_desc = '我要交稿';
                                    }elseif($isDelivery == 1){
                                        $task->employee_button = 8;
                                        $task->employee_button_desc = '等待验收';
                                    }elseif($isDelivery == 2){
                                        $task->employee_button = 9;
                                        $task->employee_button_desc = '验收通过';
                                    }elseif($isDelivery == 3){
                                        $task->employee_button = 12;
                                        $task->employee_button_desc = '维权中';
                                    }
                                }
                                break;
                            case 'employer':
                                $task->employer_button = 7;
                                $task->employer_button_desc = '我要验收';
                                break;
                        }
                        break;
                    case 8:
                        $task->status_desc = '评价中';
                        $task->time_desc = '';
                        $task_comment_time_limit = \CommonClass::getConfig('task_comment_time_limit');
                        $end = $task_comment_time_limit*24*3600;
                        $end = strtotime($task->comment_at) + $end;
                        if(time() < $end){
                            $time = \CommonClass::timediff(time(),$end);
                            $task->time_desc = '距互评结束剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';
                        }
                        switch($role){
                            case 'employee':
                                if($isToWork == 2){
                                    if($isCommentEmployer == 0){
                                        $task->employee_button = 10;
                                        $task->employee_button_desc = '我要评价';

                                        //查询验收通过的稿件
                                        $deliveryWork = WorkModel::where('work.task_id', $id)->where('uid',$uid)
                                            ->where('work.status', 3)->orderBy('id','desc')->first();
                                        if($deliveryWork){
                                            $task->delivery_work_id = $deliveryWork->id;
                                        }

                                    }elseif($isCommentEmployer == 1){
                                        $task->employee_button = 11;
                                        $task->employee_button_desc = '我要评价';
                                    }
                                }
                                break;
                            case 'employer':
                                $task->employer_button = 8;
                                $task->employer_button_desc = '我要评价';
                                break;
                        }

                        break;
                    case 9:
                        $task->status_desc = '交易完成';
                        $task->time_desc = '';
                        switch($role){
                            case 'employee':
                                $task->employee_button = 13;
                                $task->employee_button_desc = '已结束';
                                break;
                            case 'employer':
                                $task->employer_button = 10;
                                $task->employer_button_desc = '已结束';
                                break;
                        }
                        break;
                    case 10:
                        $task->status_desc = '交易失败';
                        $task->time_desc = '';
                        switch($role){
                            case 'employee':
                                $task->employee_button = 13;
                                $task->employee_button_desc = '已结束';
                                break;
                            case 'employer':
                                $task->employer_button = 10;
                                $task->employer_button_desc = '已结束';
                                break;
                        }
                        break;
                    case 11:
                        $task->status_desc = '维权中';
                        $task->time_desc = '等待后台审核';
                        switch($role){
                            case 'employee':
                                $task->employee_button = 12;
                                $task->employee_button_desc = '维权中';
                                break;
                            case 'employer':
                                $task->employer_button = 9;
                                $task->employer_button_desc = '维权中';
                                break;
                        }
                        break;
                }
                break;
            case 'zhaobiao'://招标

                $order = OrderModel::where('task_id',$id)
                    ->where('status',1)->where('code','like','ts%')->first();

                $payCaseStatus = 0;//默认没有付款方式
                $paySectionStatus = 0;//默认没有待审核的阶段交付稿件

                $payCase = TaskPayTypeModel::where('task_id',$id)->where('status',1)->first();
                if(!empty($payCase)){
                    $payCaseStatus = 1;//已经有付款方式
                }
                //查询是否有阶段交付待审核
                $paySection = TaskPaySectionModel::where('task_id',$id)->where('verify_status',0)->where('section_status',1)->first();
                if(!empty($paySection)){
                    $paySectionStatus = 1;
                }

                switch($task->status){
                    case 3:
                        $task->status_desc = '审核通过';
                        if( strtotime($task->begin_at) < time() && time() < strtotime($task->delivery_deadline)){
                            $task->status_desc = '投稿中';
                            $time = \CommonClass::timediff(time(),strtotime($task->delivery_deadline));
                            $task->time_desc = '距投稿结束剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';
                            switch($role){
                                case 'employee':
                                    $task->employee_button = 1;
                                    $task->employee_button_desc = '我要投稿';
                                    break;
                                case 'employer':
                                    $task->employer_button = 1;
                                    $task->employer_button_desc = '等待投稿';
                                    break;
                            }
                        }else{
                            $time = \CommonClass::timediff(time(),strtotime($task->begin_at));
                            $task->time_desc = '距投稿开始剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';

                        }

                        break;
                    case 4:
                        $task->status_desc = '投稿中';
                        if(time() < strtotime($task->delivery_deadline)){
                            $time = \CommonClass::timediff(time(),strtotime($task->delivery_deadline));
                            $task->time_desc = '距投稿结束剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';
                        }else{
                            $task->time_desc = '';
                        }
                        switch($role){
                            case 'employee':
                                if($isToWork == 0){
                                    $task->employee_button = 1;
                                    $task->employee_button_desc = '我要投稿';
                                }elseif($isToWork == 1){
                                    $task->employee_button = 3;
                                    $task->employee_button_desc = '等待选稿';
                                }
                                break;
                            case 'employer':
                                $task->employer_button = 1;
                                $task->employer_button_desc = '等待投稿';
                                break;
                        }

                        break;
                    case 5:
                        $task->status_desc = '选稿中';
                        $task->time_desc = '';
                        $task_select_work = \CommonClass::getConfig('bid_select_work');
                        $task_select_work = $task_select_work*24*3600 +  strtotime($task->delivery_deadline);
                        if(time() < $task_select_work){
                            $time = \CommonClass::timediff(time(),$task_select_work);
                            $task->time_desc = '距选稿结束剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';

                        }
                        switch($role){
                            case 'employee':
                                if($isToWork == 2){//中标
                                    $task->employee_button = 14;
                                    $task->employee_button_desc = '等待托管赏金';
                                }else{
                                    $task->employee_button = 5;
                                    $task->employee_button_desc = '未中标';
                                }
                                break;
                            case 'employer':
                                //是否托管赏金
                                if($task->bounty_status == 0){
                                    $task->employer_button = 4;
                                    $task->employer_button_desc = '托管赏金';
                                }

                                break;
                        }
                        break;
                    case 7:
                        $task->status_desc = '工作中';
                        $task->time_desc = '';
                        $task_delivery_max_time = \CommonClass::getConfig('bid_delivery_max_time');
                        $task_check_time_limit = \CommonClass::getConfig('bid_check_time_limit');
                        $task_max_delivery = strtotime($task->checked_at) + ($task_delivery_max_time+$task_check_time_limit)*24*3600;
                        if(time() < $task_max_delivery && $payCaseStatus == 1){
                            $time = \CommonClass::timediff(time(),$task_max_delivery);
                            $task->time_desc = '距交付结束剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';
                        }
                        switch($role){
                            case 'employee':
                                if($isToWork == 2){
                                    if($payCaseStatus == 1){
                                        if($paySectionStatus == 1){
                                            $task->employee_button = 8;
                                            $task->employee_button_desc = '等待验收';
                                        }else{
                                            $task->employee_button = 7;
                                            $task->employee_button_desc = '我要交稿';
                                            //查看第几阶段交稿
                                            $sort = 1;
                                            $paySection = TaskPaySectionModel::where('task_id',$id)->orderby('sort','asc')->get()->toArray();
                                            if(!empty($paySection)){
                                                foreach($paySection as $k => $v){
                                                    if((!empty($v['work_id']) && $v['verify_status'] == 2) || empty($v['work_id'])){
                                                        $sort = $v['sort'];
                                                        break;
                                                    }
                                                }
                                            }
                                            $task->delivery_sort = $sort;
                                        }
                                    }else{
                                        $task->employee_button = 6;
                                        $task->employee_button_desc = '确认付款方式';
                                    }
                                }

                                break;
                            case 'employer':
                                //是否托管赏金
                                if($payCaseStatus == 0){
                                    $task->employer_button = 5;
                                    $task->employer_button_desc = '确认付款方式';
                                }else{
                                    if($paySectionStatus == 1){
                                        $task->employer_button = 7;
                                        $task->employer_button_desc = '我要验收';
                                    }else{
                                        $task->employer_button = 6;
                                        $task->employer_button_desc = '等待交付';
                                    }

                                }
                                break;
                        }

                        break;
                    case 8:
                        $task->status_desc = '评价中';
                        $task->time_desc = '';
                        $task_comment_time_limit = \CommonClass::getConfig('task_comment_time_limit');
                        $end = $task_comment_time_limit*24*3600;
                        $end = strtotime($task->comment_at) + $end;
                        if(time() < $end){
                            $time = \CommonClass::timediff(time(),$end);
                            $task->time_desc = '距互评结束剩'.$time['day'].'天'.$time['hour'].'时'.$time['min'].'分';
                        }
                        //查询最后验收通过的稿件
                        $deliveryWork = WorkModel::where('work.task_id', $id)
                            ->where('work.status', 3)->orderBy('id','desc')->first();
                        if($deliveryWork){
                            $task->delivery_work_id = $deliveryWork->id;
                        }
                        switch($role){
                            case 'employee':
                                if($isToWork == 2){
                                    if($isCommentEmployer == 0){
                                        $task->employee_button = 10;
                                        $task->employee_button_desc = '我要评价';
                                        $task->to_comment_name = $task->name;
                                        $employerUserD = UserDetailModel::where('uid',$task->uid)->first();
                                        if($employerUserD){
                                            $task->to_comment_avatar = $employerUserD['avatar'] ? $domain->rule . '/' . $employerUserD['avatar'] : $employerUserD['avatar'];
                                        }
                                    }elseif($isCommentEmployer == 1){
                                        $task->employee_button = 11;
                                        $task->employee_button_desc = '我要评价';
                                    }
                                }

                                break;
                            case 'employer':
                                if($isCommentEmployee > 0){
                                    $task->employer_button = 10;
                                    $task->employer_button_desc = '我要评价';
                                }else{
                                    $task->employer_button = 8;
                                    $task->employer_button_desc = '我要评价';

                                    $bidUser = WorkModel::where('task_id',$id)->where('status',1)->first();
                                    if($bidUser){
                                        $bidUid = $bidUser->uid;
                                        //查询中标人的头像和用户名
                                        $employeeUser = UserModel::select('users.name')->where('users.id', $bidUid)->first();
                                        //中标用户名
                                        if(isset($employeeUser) && isset($employeeUser->name)){
                                            $task->to_comment_name = $employeeUser->name;
                                        }
                                        $employeeUserD = UserDetailModel::where('uid',$bidUid)->first();
                                        if($employeeUserD){
                                            $task->to_comment_avatar = $employeeUserD['avatar'] ? $domain->rule . '/' . $employeeUserD['avatar'] : $employeeUserD['avatar'];
                                        }
                                    }
                                }


                                break;
                        }
                        break;
                    case 9:
                        $task->status_desc = '交易完成';
                        $task->time_desc = '';
                        switch($role){
                            case 'employee':
                                $task->employee_button = 13;
                                $task->employee_button_desc = '已结束';
                                break;
                            case 'employer':
                                $task->employer_button = 10;
                                $task->employer_button_desc = '已结束';
                                break;
                        }
                        break;
                    case 10:
                        $task->status_desc = '交易失败';
                        $task->time_desc = '';
                        switch($role){
                            case 'employee':
                                $task->employee_button = 13;
                                $task->employee_button_desc = '已结束';
                                break;
                            case 'employer':
                                $task->employer_button = 10;
                                $task->employer_button_desc = '已结束';
                                break;
                        }
                        break;
                    case 11:
                        $task->status_desc = '维权中';
                        $task->time_desc = '等待后台审核';
                        switch($role){
                            case 'employee':
                                $task->employee_button = 12;
                                $task->employee_button_desc = '维权中';
                                break;
                            case 'employer':
                                $task->employer_button = 9;
                                $task->employer_button_desc = '维权中';
                                break;
                        }
                        break;
                }

                break;
        }

        if(isset($order) && $order){
            //查询增值服务
            $taskService = TaskServiceModel::where('task_id',$id)
                ->leftJoin('service','service.id','=','task_service.service_id')
                ->select('service.identify')->get()->toArray();
            $task->task_service = array_flatten($taskService);
        }

        //获取任务对应的投稿稿件
        $workList = [];
        //查询交付记录数量
        $deliveryCount = 0;
        //查询维权中数量
        $rightCount = 0;
        switch($role){
            case 'employer':
                //查询所有交付稿件
                $workList = WorkModel::select('work.*', 'users.id as uid', 'users.name as nickname', 'user_detail.avatar')
                    ->where('work.task_id', $id)
                    ->where('work.status', '<', 2)
                    ->with('childrenAttachment')
                    ->leftjoin('users', 'users.id', '=', 'work.uid')
                    ->leftjoin('user_detail', 'user_detail.uid', '=', 'work.uid')
                    ->orderBy('work.status','desc')->orderBy('work.created_at','desc')
                    ->get()->toArray();
                $deliveryCount = WorkModel::where('work.task_id', $id)->where('work.status', '>=', 2)->count();
                $rightCount = WorkModel::where('work.task_id', $id)->where('work.status', 4)->count();
                break;
            case 'employee':
                $workList = WorkModel::select('work.*', 'users.id as uid', 'users.name as nickname', 'user_detail.avatar')
                    ->where('work.task_id', $id)
                    ->where('work.status', '<', 2)
                    ->with('childrenAttachment')
                    ->leftjoin('users', 'users.id', '=', 'work.uid')
                    ->leftjoin('user_detail', 'user_detail.uid', '=', 'work.uid')
                    ->orderBy('work.status','desc')->orderBy('work.created_at','desc');
                $deliveryCount = WorkModel::where('work.task_id', $id)->where('work.status', '>=', 2);
                $rightCount = WorkModel::where('work.task_id', $id)->where('work.status', 4);
                //判断是否稿件屏蔽
                if($task->work_status == 1){
                    //查询自己投稿的稿件
                    $workList = $workList->where('work.uid',$uid)->get()->toArray();
                    $deliveryCount = $deliveryCount->where('work.uid',$uid)->count();
                    $rightCount = $rightCount->where('work.uid',$uid)->count();
                }else{
                    $workList = $workList->get()->toArray();
                    if($isToWork == 2){
                        $deliveryCount = $deliveryCount->count();
                    }else{
                        $deliveryCount = 0;
                    }
                    $rightCount = $rightCount->count();
                }
                break;
            case 'visitor':
                if($task->work_status == 0){
                    $workList = WorkModel::select('work.*', 'users.id as uid', 'users.name as nickname', 'user_detail.avatar')
                        ->where('work.task_id', $id)
                        ->where('work.status', '<', 2)
                        ->with('childrenAttachment')
                        ->leftjoin('users', 'users.id', '=', 'work.uid')
                        ->leftjoin('user_detail', 'user_detail.uid', '=', 'work.uid')
                        ->orderBy('work.status','desc')->orderBy('work.created_at','desc')
                        ->get()->toArray();
                    $rightCount = WorkModel::where('work.task_id', $id)->where('work.status', 4)->count();
                }
                break;
        }
        if (count($workList)) {
            foreach ($workList as $k => $v) {
                $workList[$k]['avatar'] = $workList[$k]['avatar'] ? $domain->rule . '/' . $workList[$k]['avatar'] : $workList[$k]['avatar'];
                $workList[$k]['created_at'] = date('Y',strtotime($v['created_at'])).'年'.date('m',strtotime($v['created_at'])).'月'.date('d',strtotime($v['created_at'])).'日';
                $comment = CommentModel::where('to_uid', $v['uid'])->count();
                $goodComment = CommentModel::where('to_uid', $v['uid'])->where('type', 1)->count();
                if ($comment) {
                    $workList[$k]['percent'] = number_format($goodComment / $comment, 3) * 100;
                } else {
                    $workList[$k]['percent'] = 100;
                }
            }
        }

        $task->work_list_desc = '投稿记录';
        if($task->task_type == 'zhaobiao'){
            $task->work_list_desc = '报价记录';
        }
        $task->work_list_count = count($workList);
        $task->work_list = $workList;

        $task->delivery_list_desc = '交付内容';
        $task->delivery_list_count = $deliveryCount;

        $task->right_list_desc = '交易维权';
        $task->right_list_count = $rightCount;

        //查询评价数量
        $commentCount = CommentModel::where('task_id',$id)->count();
        $task->comment_list_desc = '双方互评';
        $task->comment_list_count = $commentCount;

        //去掉多余数据
        unset($task->type_id,$task->cate_id,$task->region_limit,$task->updated_at,$task->verified_at,$task->end_at,$task->delivery_deadline,$task->selected_work_at,$task->publicity_at,$task->checked_at,$task->comment_at,$task->show_cash,$task->real_cash,$task->deposit_cash,$task->province,$task->city,$task->area,$task->username,$task->service,$task->task_success_draw_ratio,$task->task_fail_draw_ratio,$task->kee_status);

        return $this->formateResponse(1000, 'success', $task);
    }

    /**
     * 交付列表
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function deliveryList(Request $request)
    {
        $id = intval($request->get('id'));
        $task = TaskModel::findById($id);
        if (!$task) {
            return $this->formateResponse(2001, '未找到与之对应ID的任务信息');
        }
        $role = 'visitor';
        $uid = 0;
        if ($request->get('token')) {
            $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
            $uid = $tokenInfo['uid'];
            //判断是否是雇主
            if($uid == $task->uid){
                $role = 'employer';//雇主

            }else{
                $role = 'employee';
            }
        }
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        $workList = [];
        $commentUid = [];
        switch($role){
            case 'employer':
                //查询所有交付稿件
                $workList = WorkModel::select('work.*', 'users.id as uid', 'users.name as nickname', 'user_detail.avatar')
                    ->where('work.task_id', $id)
                    ->where('work.status', '>=', 2)
                    ->with('childrenAttachment')
                    ->leftjoin('users', 'users.id', '=', 'work.uid')
                    ->leftjoin('user_detail', 'user_detail.uid', '=', 'work.uid')
                    ->orderBy('work.created_at','desc')
                    ->get()->toArray();
                $comment = CommentModel::where('task_id',$id)->where('from_uid',$uid)->select('to_uid')->get()->toArray();
                $commentUid = array_flatten($comment);
                break;
            case 'employee':
                $workList = WorkModel::select('work.*', 'users.id as uid', 'users.name as nickname', 'user_detail.avatar')
                    ->where('work.task_id', $id)
                    ->where('work.status', '>=', 2)
                    ->with('childrenAttachment')
                    ->leftjoin('users', 'users.id', '=', 'work.uid')
                    ->leftjoin('user_detail', 'user_detail.uid', '=', 'work.uid')
                    ->orderBy('work.created_at','desc');
                //判断是否稿件屏蔽
                if($task->work_status == 1){
                    //查询自己投稿的稿件
                    $workList = $workList->where('work.uid',$uid)->get()->toArray();
                }else{
                    $workList = $workList->get()->toArray();
                }
                break;
            case 'visitor':
                if($task->work_status == 1){
                    $workList = [];
                }else{
                    $workList = WorkModel::select('work.*', 'users.id as uid', 'users.name as nickname', 'user_detail.avatar')
                        ->where('work.task_id', $id)
                        ->where('work.status', '>=', 2)
                        ->with('childrenAttachment')
                        ->leftjoin('users', 'users.id', '=', 'work.uid')
                        ->leftjoin('user_detail', 'user_detail.uid', '=', 'work.uid')
                        ->orderBy('work.created_at','desc')
                        ->get()->toArray();
                }
                break;
        }
        if (count($workList)) {
            if($task->task_type == 'zhaobiao'){
                //查询该任务的交付阶段
                $paySection = TaskPaySectionModel::where('task_id',$id)->select('work_id','name')->get()->toArray();
                $newPay = [];
                if(!empty($paySection)){
                    $newPay = array_reduce($paySection,function(&$newPay,$v){
                        if($v['work_id']){
                            $newPay[$v['work_id']] = $v['name'];
                        }
                        return $newPay;
                    });
                }
            }
            foreach ($workList as $k => $v) {
                $workList[$k]['avatar'] = $workList[$k]['avatar'] ? $domain->rule . '/' . $workList[$k]['avatar'] : $workList[$k]['avatar'];
                $workList[$k]['created_at'] = date('Y',strtotime($v['created_at'])).'年'.date('m',strtotime($v['created_at'])).'月'.date('d',strtotime($v['created_at'])).'日';
                $comment = CommentModel::where('to_uid', $v['uid'])->count();
                $goodComment = CommentModel::where('to_uid', $v['uid'])->where('type', 1)->count();
                if ($comment) {
                    $workList[$k]['percent'] = number_format($goodComment / $comment, 3) * 100;
                } else {
                    $workList[$k]['percent'] = 100;
                }
                $workList[$k]['is_commnet'] = 0;//默认不能评价
                if($task->task_type == 'xuanshang' && $v['status'] == 3 && $role=='employer'){
                    //查看是否需要评价
                    if(empty($commentUid) || (!empty($commentUid) && !in_array($v['uid'],$commentUid))){
                        $workList[$k]['is_commnet'] = 1;// 可以评价威客
                    }
                }
                $workList[$k]['delivery_sort'] = '';
                if($task->task_type == 'zhaobiao' && isset($newPay) &&in_array($v['id'],array_keys($newPay))){
                    $workList[$k]['delivery_sort'] = $newPay[$v['id']];
                }
            }
        }
        return $this->formateResponse(1000, 'success', $workList);
    }

    /**
     * 维权稿件列表(某个任务)
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function rightList(Request $request)
    {
        $id = intval($request->get('id'));
        $task = TaskModel::findById($id);
        if (!$task) {
            return $this->formateResponse(2001, '未找到与之对应ID的任务信息');
        }
        $role = 'visitor';
        $uid = 0;
        if ($request->get('token')) {
            $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
            $uid = $tokenInfo['uid'];
            //判断是否是雇主
            if($uid == $task->uid){
                $role = 'employer';//雇主

            }else{
                $role = 'employee';
            }
        }
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        $workList = [];
        switch($role){
            case 'employer':
                //查询所有交付稿件
                $workList = WorkModel::select('work.*', 'users.id as uid', 'users.name as nickname', 'user_detail.avatar')
                    ->where('work.task_id', $id)
                    ->where('work.status', 4)
                    ->with('childrenAttachment')
                    ->leftjoin('users', 'users.id', '=', 'work.uid')
                    ->leftjoin('user_detail', 'user_detail.uid', '=', 'work.uid')
                    ->orderBy('work.created_at','desc')
                    ->get()->toArray();
                break;
            case 'employee':
                $workList = WorkModel::select('work.*', 'users.id as uid', 'users.name as nickname', 'user_detail.avatar')
                    ->where('work.task_id', $id)
                    ->where('work.status', 4)
                    ->with('childrenAttachment')
                    ->leftjoin('users', 'users.id', '=', 'work.uid')
                    ->leftjoin('user_detail', 'user_detail.uid', '=', 'work.uid')
                    ->orderBy('work.created_at','desc');
                //判断是否稿件屏蔽
                if($task->work_status == 1){
                    //查询自己投稿的稿件
                    $workList = $workList->where('work.uid',$uid)->get()->toArray();
                }else{
                    $workList = $workList->get()->toArray();
                }
                break;
            case 'visitor':
                if($task->work_status == 1){
                    $workList = [];
                }else{
                    $workList = WorkModel::select('work.*', 'users.id as uid', 'users.name as nickname', 'user_detail.avatar')
                        ->where('work.task_id', $id)
                        ->where('work.status', 4)
                        ->with('childrenAttachment')
                        ->leftjoin('users', 'users.id', '=', 'work.uid')
                        ->leftjoin('user_detail', 'user_detail.uid', '=', 'work.uid')
                        ->orderBy('work.created_at','desc')
                        ->get()->toArray();
                }
                break;
        }
        if (count($workList)) {
            foreach ($workList as $k => $v) {
                $workList[$k]['avatar'] = $workList[$k]['avatar'] ? $domain->rule . '/' . $workList[$k]['avatar'] : $workList[$k]['avatar'];
                $workList[$k]['created_at'] = date('Y',strtotime($v['created_at'])).'年'.date('m',strtotime($v['created_at'])).'月'.date('d',strtotime($v['created_at'])).'日';
                $comment = CommentModel::where('to_uid', $v['uid'])->count();
                $goodComment = CommentModel::where('to_uid', $v['uid'])->where('type', 1)->count();
                if ($comment) {
                    $workList[$k]['percent'] = number_format($goodComment / $comment, 3) * 100;
                } else {
                    $workList[$k]['percent'] = 100;
                }
            }
        }
        return $this->formateResponse(1000, 'success', $workList);
    }

    /**
     *  任务的评价列表
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function commentList(Request $request)
    {
        $id = intval($request->get('id'));//任务id
        $task = TaskModel::findById($id);
        if (!$task) {
            return $this->formateResponse(2001, '未找到与之对应ID的任务信息');
        }

        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        $commentList = CommentModel::select('comments.*', 'users.id as uid', 'users.name as nickname', 'user_detail.avatar')
            ->where('comments.task_id',$id)
            ->leftjoin('users', 'users.id', '=', 'comments.from_uid')
            ->leftjoin('user_detail', 'user_detail.uid', '=', 'comments.from_uid')
            ->orderBy('comments.created_at','desc')
            ->get()->toArray();

        if (count($commentList)) {
            foreach ($commentList as $k => $v) {
                $commentList[$k]['avatar'] = $commentList[$k]['avatar'] ? $domain->rule . '/' . $commentList[$k]['avatar'] : $commentList[$k]['avatar'];
                if($v['to_uid'] == $task->uid){
                    $commentList[$k]['to_desc'] = '给雇主的评价';
                    $commentList[$k]['to_status'] = 1;
                }else{
                    $commentList[$k]['to_desc'] = '给威客的评价';
                    $commentList[$k]['to_status'] = 2;
                }

            }
        }
        return $this->formateResponse(1000, 'success', $commentList);
    }


    /**
     * 稿件详情
     * @author quanke
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function showWorkDetail(Request $request)
    {
        $work = WorkModel::select('work.*', 'users.name as nickname', 'user_detail.avatar','users.email_status')
            ->where('work.id', intval($request->get('id')))
            ->leftjoin('users', 'users.id', '=', 'work.uid')
            ->leftjoin('user_detail', 'user_detail.uid', '=', 'work.uid')
            ->first();
        if (!$work) {
            return $this->formateResponse(2001, '未找到对应ID的稿件信息');
        }
        $task = TaskModel::findById($work['task_id']);
        $role = 'visitor';
        if ($request->get('token')) {
            $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
            $uid = $tokenInfo['uid'];
            //判断是否是雇主
            if($uid == $task->uid){
                $role = 'employer';//雇主

            }else{
                $role = 'employee';
            }
        }
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        $work->task_status = $task->status;
        $work->task_type = $task->task_type;
        $work->button_status = 0;//0:不显示 1:选他 2:验收维权 3:评价
        if(isset($role) && $role == 'employer'){
            switch($work->status){
                case 0:
                    if($task->task_type == 'xuanshang' && in_array($task->status,[4,5])){
                        $work->button_status = 1;
                    }elseif($task->task_type == 'zhaobiao' && $task->status == 4){
                        $work->button_status = 1;
                    }
                    break;
                case 2:
                    if($task->status == 7){
                        $work->button_status = 2;//验收或维权
                    }
                    break;
                case 3://验收通过
                    if($task->status == 8 && $task->task_type == 'xuanshang'){//互评中状态
                        $isComment = CommentModel::where('task_id', $work->task_id)->where('from_uid', $task->uid)->where('to_uid', $work->uid)->first();
                        if(!$isComment){
                            $work->button_status = 3;
                        }
                    }
                    break;

            }
        }
        $work->delivery_sort = '';
        //获取稿件交付阶段
        if($work->status >= 2 && $task->task_type == 'zhaobiao'){
            $paySection = TaskPaySectionModel::where('work_id',intval($request->get('id')))->select('name')->first();
            if($paySection){
                $work->delivery_sort = $paySection->name;
            }
        }


        //获取稿件附件
        $arrWorkIds = WorkAttachmentModel::findById($work->id);

        $work->avatar = $work->avatar ? $domain->rule . '/' . $work->avatar : $work->avatar;
        if (count($arrWorkIds)) {
            $attachment = AttachmentModel::findByIds($arrWorkIds);
            if (isset($attachment)) {
                foreach ($attachment as $k => $v) {
                    $attachment[$k]['url'] = $attachment[$k]['url'] ? $domain->rule . '/' . $attachment[$k]['url'] : $attachment[$k]['url'];
                }
            }
        } else {
            $attachment = '';
        }
        //好评率
        $comments = CommentModel::where('to_uid',$work->uid)->count();
        $good_comments = CommentModel::where('to_uid',$work->uid)->where('type',1)->count();
        if($comments==0){
            $applause_rate = 100;
        }else{
            $applause_rate = floor(($good_comments/$comments)*100);
        }
        $work->applauseRate = $applause_rate;
        //完成任务数
        $work->complete = $comments;
        $work->attachment = $attachment;
        //用户认证关系
        $userAuthOne = AuthRecordModel::select('auth_code')->where('uid', $work->uid)->where('status', 2)
            ->whereIn('auth_code',['bank','alipay'])->get()->toArray();
        $userAuthOne = array_flatten($userAuthOne);
        $userAuthTwo = AuthRecordModel::select('auth_code')->where('uid', $work->uid)->where('status', 1)
            ->whereIn('auth_code',['realname','enterprise'])->get()->toArray();
        $userAuthTwo = array_flatten($userAuthTwo);
        $emailAuth = [];
        if($work->email_status == 2){
            $emailAuth = ['email'];
        }
        $userAuth = array_unique(array_merge($userAuthOne,$userAuthTwo,$emailAuth));
        if(in_array('enterprise',$userAuth)){
            $work->isEnterprise = 1;
        }else{
            $work->isEnterprise = 0;
        }
        if(in_array('bank',$userAuth)){
            $work->bank = 1;
        }else{
            $work->bank = 0;
        }
        if(in_array('alipay',$userAuth)){
            $work->alipay = 1;
        }else{
            $work->alipay = 0;
        }
        if(in_array('email',$userAuth)){
            $work->email = 1;
        }else{
            $work->email = 0;
        }
        if(in_array('realname',$userAuth)){
            $work->realname = 1;
        }else{
            $work->realname = 0;
        }
        return $this->formateResponse(1000, 'success', $work);
    }

    /**
     * 维权详情
     * @author quanke
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function rightDetail(Request $request)
    {
        $workId = $request->get('work_id');
        if(!$workId){
            return $this->formateResponse(2001,'缺少参数');
        }
        //查询稿件维权信息
        $right = TaskRightsModel::select('type','desc','from_uid')->where('work_id',$workId)->first();
        if(!$request){
            return $this->formateResponse(2001,'参数错误');
        }
        switch($right->type){
            case 1:
                $right->type = '违规信息';
                break;
            case 2:
                $right->type = '虚假交换';
                break;
            case 3:
                $right->type = '涉嫌抄袭';
                break;
            case 4:
                $right->type = '其他';
                break;
        }
        //查询维权人的信息
        $uid = $right->from_uid;
        //好评率
        $comments = CommentModel::where('to_uid',$uid)->count();
        $good_comments = CommentModel::where('to_uid',$uid)->where('type',1)->count();
        if($comments==0){
            $applause_rate = 100;
        }else{
            $applause_rate = floor(($good_comments/$comments)*100);
        }
        $right->applauseRate = $applause_rate;
        //完成任务数
        $right->complete = $comments;
        //用户名
        $user = UserModel::where('id',$uid)->select('name','email_status')->first();
        if(!$user){
            return $this->formateResponse(2001,'维权人不存在');
        }
        $right->username = $user->name;
        $userInfo = UserDetailModel::where('uid',$uid)->select('avatar')->first();
        if(!$userInfo){
            return $this->formateResponse(2001,'维权人不存在');
        }
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        $right->avarar = $userInfo->avatar ? $domain->rule . '/' . $userInfo->avatar : $userInfo->avatar;
        //用户认证关系
        $userAuthOne = AuthRecordModel::select('auth_code')->where('uid', $uid)->where('status', 2)
            ->whereIn('auth_code',['bank','alipay'])->get()->toArray();
        $userAuthOne = array_flatten($userAuthOne);
        $userAuthTwo = AuthRecordModel::select('auth_code')->where('uid', $uid)->where('status', 1)
            ->whereIn('auth_code',['realname','enterprise'])->get()->toArray();
        $userAuthTwo = array_flatten($userAuthTwo);
        $emailAuth = [];
        if($user->email_status == 2){
            $emailAuth = ['email'];
        }

        $userAuth = array_unique(array_merge($userAuthOne,$userAuthTwo,$emailAuth));
        if(in_array('enterprise',$userAuth)){
            $right->isEnterprise = 1;
        }else{
            $right->isEnterprise = 0;
        }
        if(in_array('bank',$userAuth)){
            $right->bank = 1;
        }else{
            $right->bank = 0;
        }
        if(in_array('alipay',$userAuth)){
            $right->alipay = 1;
        }else{
            $right->alipay = 0;
        }
        if(in_array('email',$userAuth)){
            $right->email = 1;
        }else{
            $right->email = 0;
        }
        if(in_array('realname',$userAuth)){
            $right->realname = 1;
        }else{
            $right->realname = 0;
        }

        return $this->formateResponse(1000,'success',$right);
    }

    /**
     * 获取省市区信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function district(Request $request)
    {
        $area_data = DistrictModel::where('upid', 0)->select('id', 'upid', 'name', 'spelling')->get()->toArray();
        if (empty($area_data)) {
            return $this->formateResponse(2002, '暂无省份信息');
        }
        $province = [];
        $province = array_filter(array_flatten($area_data));
        $city = DistrictModel::whereIn('upid', $province)->select('id', 'name', 'upid', 'spelling')->get()->toArray();
        if (empty($city)) {
            return $this->formateResponse(2003, '暂无城市信息');
        }
        foreach ($area_data as $pk => $pv) {
            foreach ($city as $ck => $cv) {
                if ($pv['id'] == $cv['upid']) {
                    $area_data[$pk]['child'][] = $cv;
                }
            }
        }
        return $this->formateResponse(1000, '获取地区信息成功', $area_data);
    }

    /**
     * 获取服务商列表信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function serviceList(Request $request)
    {
        $userInfo = userModel::where('users.status', '<>', 2)
            ->leftjoin('user_detail', 'users.id', '=', 'user_detail.uid');
        if ($request->get('name')) {
            $userInfo = $userInfo->where('users.name', 'like', '%' . $request->get('name') . '%');
        }
        if ($request->get('type')) {
            if ($request->get('type') == '1') {
                $userInfo = $userInfo->orderBy('users.created_at', 'desc');//按照最新排序
            } else {
                $userInfo = $userInfo->orderBy('user_detail.employee_praise_rate', 'desc');//按照好评量排序
            }
        }
        if ($request->get('category')) {
            $category = intval($request->get('category'));
            $category_data = TaskCateModel::findById($category);
            //检查二级
            if ($category_data['pid'] == 0) {
                return $this->formateResponse('1065', '筛选失败，不能直接筛选一级！');
            }
            //查询tagid
            $tag_ids = TagsModel::where('cate_id', $category_data['id'])->first();
            //查询用户id
            $user_ids = UserTagsModel::where('tag_id', $tag_ids['id'])->lists('uid');
            $userInfo = $userInfo->whereIn('users.id', $user_ids);
        }
        $userInfo = $userInfo->select('users.id', 'users.name', 'user_detail.avatar')->paginate()->toArray();
        if ($userInfo['total']) {
            $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();//获取域名信息
            foreach ($userInfo['data'] as $k => $v) {
                $userInfo['data'][$k]['avatar'] = $userInfo['data'][$k]['avatar'] ? $domain->rule . '/' . $userInfo['data'][$k]['avatar'] : $userInfo['data'][$k]['avatar'];
                $userTagRelation = UserTagsModel::where('uid', $v['id'])->select('tag_id')->get()->toArray();
                if (count($userTagRelation)) {
                    $tagId = array_unique(array_flatten($userTagRelation));
                    $tagNameInfo = TagsModel::whereIn('id', $tagId)->select('tag_name')->get()->toArray();
                    $tagName = array_unique(array_flatten($tagNameInfo));
                    $userInfo['data'][$k]['tags'] = $tagName;
                } else {
                    $userInfo['data'][$k]['tags'] = [];
                }

                $comment = CommentModel::where('to_uid', $v['id'])->count();
                $goodComment = CommentModel::where('to_uid', $v['id'])->where('type', 1)->count();
                if ($comment) {
                    $userInfo['data'][$k]['percent'] = number_format($goodComment / $comment, 3) * 100;
                } else {
                    $userInfo['data'][$k]['percent'] = 100;
                }
            }
        }
        return $this->formateResponse(1000, '获取服务商列表信息成功', $userInfo);

    }

    /**
     * 获取雇主的我的信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function buyerInfo(Request $request)
    {
        // if($request->input('token')){
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $userInfo = UserModel::leftjoin('user_detail', 'users.id', '=', 'user_detail.uid')->where('users.id', $tokenInfo['uid'])->select('users.name as nickname', 'avatar')->first()->toArray();
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        $userInfo['avatar'] = $userInfo['avatar'] ? $domain->rule . '/' . $userInfo['avatar'] : $userInfo['avatar'];
        $taskNum = TaskModel::where('uid', $tokenInfo['uid'])->count();
        $userInfo['taskNum'] = $taskNum;
        $speedScore = CommentModel::where('to_uid', $tokenInfo['uid'])->where('comment_by', 0)->avg('speed_score');
        $qualityScore = CommentModel::where('to_uid', $tokenInfo['uid'])->where('comment_by', 0)->avg('quality_score');
        $speedScore = number_format($speedScore, 1);
        $qualityScore = number_format($qualityScore, 1);
        $userInfo['speed_score'] = $speedScore != 0.0 ? $speedScore : 5.0;
        $userInfo['attitude_score'] = $qualityScore != 0.0 ? $qualityScore : 5.0;
        /*}
        else{
            $userInfo = [];
            $userInfo['nickname'] = '游客';
            $userInfo['avatar'] = null;
            $userInfo['taskNum'] = 0;
            $userInfo['speed_score'] = 5.0;
            $userInfo['attitude_score'] = 5.0;
        }*/
        return $this->formateResponse(1000, '获取雇主信息成功', $userInfo);

    }


    /**
     * 获取威客的我的信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function workerInfo(Request $request)
    {
        //if($request->input('token')){
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $userInfo = UserModel::leftjoin('user_detail', 'users.id', '=', 'user_detail.uid')->where('users.id', $tokenInfo['uid'])->select('users.name as nickname', 'avatar')->first()->toArray();
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        $userInfo['avatar'] = $userInfo['avatar'] ? $domain->rule . '/' . $userInfo['avatar'] : $userInfo['avatar'];
        $taskNum = WorkModel::where('uid', $tokenInfo['uid'])->where('status', 3)->count();
        $userInfo['taskNum'] = $taskNum;
        $speedScore = CommentModel::where('to_uid', $tokenInfo['uid'])->where('comment_by', 1)->avg('speed_score');
        $qualityScore = CommentModel::where('to_uid', $tokenInfo['uid'])->where('comment_by', 1)->avg('quality_score');
        $attitudeScore = CommentModel::where('to_uid', $tokenInfo['uid'])->where('comment_by', 1)->avg('attitude_score');
        $speedScore = number_format($speedScore, 1);
        $qualityScore = number_format($qualityScore, 1);
        $attitudeScore = number_format($attitudeScore, 1);
        $userInfo['speed_score'] = $speedScore != 0.0 ? $speedScore : 5.0;
        $userInfo['attitude_score'] = $attitudeScore != 0.0 ? $attitudeScore : 5.0;
        $userInfo['quality_score'] = $qualityScore != 0.0 ? $qualityScore : 5.0;
        /*}
        else{
            $userInfo = [];
            $userInfo['nickname'] = '游客';
            $userInfo['avatar'] = null;
            $userInfo['taskNum'] = 0;
            $userInfo['speed_score'] = 5.0;
            $userInfo['attitude_score'] = 5.0;
            $userInfo['quality_score'] = 5.0;
        }*/
        return $this->formateResponse(1000, '获取威客信息成功', $userInfo);

    }


    /**
     * 意见反馈
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function feedbackInfo(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $validator = Validator::make($request->all(), [
            'desc' => 'required|max:255'
        ],
            [
                'desc.required' => '请输入投诉建议',
                'desc.max' => '投诉建议字数超过限制'
            ]);
        $error = $validator->errors()->all();
        if (count($error)) {
            return $this->formateResponse(1001, $error[0]);
        }
        $newdata = [
            'desc' => $request->get('desc'),
            'created_time' => date('Y-m-d h:i:s', time()),
            'uid' => $tokenInfo['uid']
        ];
        $userInfo = UserModel::where('users.id', $tokenInfo['uid'])
            ->leftjoin('user_detail', 'users.id', '=', 'user_detail.uid')
            ->select('user_detail.mobile')
            ->first();
        if (isset($userInfo)) {
            $newdata['phone'] = $userInfo->mobile;
        }
        $res = FeedbackModel::create($newdata);
        if ($res) {
            return $this->formateResponse(1000, '反馈意见提交成功');
        }
        return $this->formateResponse(1060, '反馈意见提交失败');
    }


    /**
     * 帮助中心
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function helpCenter(Request $request)
    {
        $categoryInfo = ArticleCategoryModel::where('cate_name', '常见问题')->select('id')->first();
        if (isset($categoryInfo)) {
            $category = ArticleCategoryModel::where('pid', $categoryInfo->id)->select('id')->get()->toArray();
            if (count($category)) {
                $category = array_flatten($category);
                $articleInfo = ArticleModel::whereIn('cat_id', $category)->select('title', 'content')->paginate()->toArray();
                if (!$articleInfo['total']) {
                    $articleInfo = [];
                } else {
                    foreach ($articleInfo['data'] as $k => $v) {
                        $articleInfo['data'][$k]['content'] = htmlspecialchars_decode($v['content']);
                    }
                }
            } else {
                $articleInfo = [];
            }
        } else {
            $articleInfo = [];
        }

        return $this->formateResponse(1000, '获取帮助中心信息成功', $articleInfo);
    }


    /**
     * 威客详情
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function workerDetail(Request $request)
    {
        if (!$request->get('id')) {
            return $this->formateResponse(1061, '传送参数不能为空');
        }
        $tagName = $userInfo = [];
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        $userDetail = UserModel::select('users.name as nickname', 'user_detail.avatar')
            ->leftjoin('user_detail', 'users.id', '=', 'user_detail.uid')
            ->where('users.id', intval($request->get('id')))
            ->first();
        if ($request->get('token')) {
            $tokenInfo = Crypt::decrypt($request->get('token'));
            $userFocus = UserFocusModel::where('uid', $tokenInfo['uid'])->where('focus_uid', intval($request->get('id')))->first();
            if (isset($userFocus)) {
                $userInfo['focused'] = 1;
            } else {
                $userInfo['focused'] = 0;
            }
        } else {
            $userInfo['focused'] = 0;
        }

        if (!isset($userDetail)) {
            return $this->formateResponse(1062, '传送参数错误');
        }
        $userInfo['nickname'] = $userDetail->nickname;
        $userInfo['avatar'] = $userDetail->avatar ? $domain->rule . '/' . $userDetail->avatar : $userDetail->avatar;
        $userTagRelation = UserTagsModel::where('uid', intval($request->get('id')))->select('tag_id')->get()->toArray();
        if (count($userTagRelation)) {
            $tagId = array_unique(array_flatten($userTagRelation));
            $tagNameInfo = TagsModel::whereIn('id', $tagId)->select('tag_name')->get()->toArray();
            $tagName = array_unique(array_flatten($tagNameInfo));
        }
        $userInfo['tagName'] = $tagName;

        $comment = CommentModel::where('to_uid', $request->get('id'))->count();
        $goodComment = CommentModel::where('to_uid', $request->get('id'))->where('type', 1)->count();
        if ($comment) {
            $userInfo['percent'] = number_format($goodComment / $comment, 3) * 100;
        } else {
            $userInfo['percent'] = 0;
        }
        $taskNum = WorkModel::where('uid', $request->get('id'))->where('status', 3)->count();
        $userInfo['taskNum'] = $taskNum;
        $commentInfo = CommentModel::where('to_uid', $request->get('id'))->where('comment_by', 1)->select('speed_score', 'quality_score', 'attitude_score')->first();
        if (isset($commentInfo)) {
            $userInfo['speed_score'] = $commentInfo->speed_score;
            $userInfo['attitude_score'] = $commentInfo->attitude_score;
            $userInfo['quality_score'] = $commentInfo->quality_score;
        } else {
            $userInfo['speed_score'] = 5.0;
            $userInfo['attitude_score'] = 5.0;
            $userInfo['quality_score'] = 5.0;
        }
        $caseInfo = SuccessCaseModel::where('uid', intval($request->get('id')))->select('*')->get()->toArray();
        if (count($caseInfo)) {
            foreach ($caseInfo as $k => $v) {
                $caseInfo[$k]['pic'] = $caseInfo[$k]['pic'] ? $domain->rule . '/' . $caseInfo[$k]['pic'] : $caseInfo[$k]['pic'];
                $caseInfo[$k]['desc'] = htmlspecialchars_decode($caseInfo[$k]['desc']);
            }
        }
        $userInfo['caseInfo'] = $caseInfo;
        return $this->formateResponse(1000, '获取威客信息成功', $userInfo);
    }

    /**
     * 检查是否需要修改密码
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function passwordCheck(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $userInfo = UserModel::where('id', $tokenInfo['uid'])->select('password', 'alternate_password')->first();
        if (!isset($userInfo)) {
            return $this->formateResponse(1062, '传送参数错误');
        }
        $status = 0;
        if ($userInfo->password == $userInfo->alternate_password) {
            $status = 1;
        }
        return $this->formateResponse(1000, '获取状态成功', ['status' => $status]);
    }

    /**
     *用户余额和每日提现金额查询
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function moneyConfig(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $userInfo = UserDetailModel::where('uid', $tokenInfo['uid'])->select('balance')->first();
        if (!isset($userInfo)) {
            return $this->formateResponse(1063, '传送参数错误');
        }
        $config = ConfigModel::getConfigByAlias('cash')->toArray();
        $money = json_decode($config['rule'], true);
        $data = array(
            'balance' => $userInfo->balance,
            'withdrawals' => $money['withdraw_max']
        );
        return $this->formateResponse(1000, '获取金额信息成功', $data);
    }


    /**
     *获取用户充值信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getCash(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $userInfo = UserDetailModel::select('balance')->where('uid', $tokenInfo['uid'])->first();
        $payConfig = ConfigModel::getConfigByType('thirdpay');

        if (!empty($userInfo)) {
            $data = array(
                'balance' => $userInfo->balance,
                'payConfig' => $payConfig
            );
        }
        return $this->formateResponse(1000, '获取用户充值信息成功', $data);
    }


    /**
     * 获取首页热门任务
     * update by quanke
     * 20180110
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function hotTask(Request $request)
    {
        //获取推荐位app热门任务
        $reTarget = RePositionModel::where('code', 'APP_HOT_TASK')->where('is_open', '1')->select('id', 'name')->first();
        if ($reTarget->id) {
            $recommend = RecommendModel::where('position_id', $reTarget->id)
                ->where('is_open', 1)
                ->where(function ($recommend) {
                    $recommend->where('end_time', '0000-00-00 00:00:00')
                        ->orWhere('end_time', '>', date('Y-m-d h:i:s', time()));
                })
                ->select('recommend_id')
                ->get()
                ->toArray();
            if (isset($recommend)) {
                $task_id = array_flatten($recommend);
                //获取推荐任务信息数组
                $recommend = TaskModel::whereIn('task.id', $task_id)
                    ->leftjoin('cate', 'task.cate_id', '=', 'cate.id')
                    ->leftJoin('task_type','task.type_id','=','task_type.id')
                    ->select('task.id', 'task.title', 'task.view_count', 'task.delivery_count', 'task.created_at', 'task.bounty' ,'task.bounty_status','cate.name', 'task.uid','task_type.alias as task_type')
                    ->where('task.status','>',2)//任务审核通过
                        ->where(function($query){
                            $query->where(function($querys){//悬赏要托管赏金
                                $querys->where('task.bounty_status',1)->where('task_type.alias','xuanshang');
                            })->orwhere(function($querys){
                                $querys->where('task_type.alias','zhaobiao');
                            });
                        })
                    ->where('task.begin_at','<',date('Y-m-d H:i:s',time()))//已经开始投稿的任务
                    ->where('task.status','!=',10)//任务没有失败
                    ->orderBy('task.top_status','desc')//增值服务排序
                    ->orderBy('task.created_at', 'desc')
                    ->get()
                    ->toArray();
                if(!empty($recommend)) {
                    //重组任务数据 拼接购买的增值服务等
                    $recommend = TaskModel::dealTaskArr($recommend);
                }

            }
            return $this->formateResponse(1000, '获取热门任务信息成功', $recommend);

        } else {
            return $this->formateResponse(1053, '暂无热门任务信息');
        }
    }


    /**
     * 更新地区表中的拼音字段
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function updateSpelling()
    {
        $provinceIds = DistrictModel::where('upid', 0)->select('id')->get()->toArray();
        $id = array_flatten($provinceIds);
        $province = DistrictModel::where('upid', 0)->select('id', 'name')->get()->toArray();
        $city = DistrictModel::whereIn('upid', $id)->select('id', 'name')->get()->toArray();
        $area_data = array_merge($province, $city);


        set_time_limit(180);
        $except = [
            '深水埗区', '埇桥区', '浉河区', '浭阳街道', '临洺关镇', '洺州镇', '勍香镇', '牤牛营子乡', '濛江乡', '栟茶镇', '澥浦镇', '浬浦镇', '富堨镇'
        ];
        foreach ($area_data as $k => $v) {
            if (!in_array($v['name'], $except)) {
                $py = \StringHandleClass::encode($v['name'], 'all');
                $py = str_replace(' ', '', trim($py));
                $newSpelling = [//拼音字段
                    'spelling' => $py
                ];
                DistrictModel::where('id', $v['id'])->update($newSpelling);
            }

        }
        return $this->formateResponse(1000, '更新成功');
    }


    /**
     * 获取一级分类下的任务信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function taskByCate(Request $request)
    {
        if (!$request->get('cate_id')) {
            return $this->formateResponse(1052, '传送参数不能为空');
        }
        $cate_id = TaskCateModel::where('pid', $request->get('cate_id'))->select('id')->get()->toArray();
        $cate_id = array_flatten($cate_id);

        $tasks = TaskModel::select('task.id', 'task.title', 'task.view_count', 'task.delivery_count', 'task.created_at', 'task.bounty' ,'task.bounty_status','cate.name', 'task.uid','task_type.alias as task_type')
            ->leftjoin('cate', 'task.cate_id', '=', 'cate.id')
            ->leftJoin('task_type','task.type_id','=','task_type.id')
            ->where('task.status','>',2)//任务审核通过
            ->where('task.begin_at','<',date('Y-m-d H:i:s',time()))//已经开始投稿的任务
            ->where('task.status','<=',9)
            ->whereIn('task.cate_id',$cate_id)->orderBy('top_status','desc')->orderBy('view_count','desc')->limit(8)->get()->toArray();
        if(!empty($tasks)){
            $tasks = TaskModel::dealTaskArr($tasks);
        }

        return $this->formateResponse(1000, '获取分类下的任务信息成功', $tasks);


    }

    /**
     * 关于我们
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function aboutUs(Request $request)
    {
        $categoryInfo = ArticleCategoryModel::where('cate_name', '关于我们')->select('id')->first();
        if (isset($categoryInfo)) {
            $articleInfo = ArticleModel::where('cat_id', $categoryInfo->id)->select('title', 'content')->first();
            if (!empty($articleInfo)) {
                $articleInfo->content = htmlspecialchars_decode($articleInfo->content);
            } else {
                $articleInfo = [];
            }
        } else {
            $articleInfo = [];
        }

        return $this->formateResponse(1000, '获取关于我们信息成功', $articleInfo);
    }

}