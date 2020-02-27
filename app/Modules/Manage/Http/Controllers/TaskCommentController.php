<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Modules\User\Model\CommentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskCommentController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->setTitle('互评管理');
    }

    /**
     * 互评管理列表
     *
     * @param Request $request
     * @return mixed
     */
    public function getCommentList(Request $request)
    {
        $data = $request->all();
        $query = CommentModel::select('comments.*', 'ud.name as from_nickname', 'userd.name as to_nickname');
        //用户名筛选筛选
        if ($request->get('commentId')) {
            $query = $query->where('ud.name', 'like', '%' . $request->get('commentId') . '%');
        }
        //来自筛选
        if ($request->get('from') && $request->get('from') != 0) {
            if ($request->get('from') == 1) {
                $query = $query->where('comments.from_uid', '=', 'tk.uid');
            } else {
                $query = $query->where('comments.from_uid', '!=', 'tk.uid');
            }

        }
        //排序筛选
        $orderBy = 'id';
        if ($request->get('orderBy')) {
            $orderBy = $request->get('orderBy');
        }
        $orderByType = 'acs';
        if ($request->get('orderByType')) {
            $orderByType = $request->get('orderByType');
        }
        //分页条数筛选
        $page_size = 10;
        if ($request->get('pageSize')) {
            $page_size = $request->get('pageSize');
        }
        //时间
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $query = $query->where('comments.created_at', '>',$start);
        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d H:i:s',strtotime($end));
            $query = $query->where('comments.created_at', '<',$end);
        }
        $comments = $query->join('users as ud', 'ud.id', '=', 'comments.from_uid')
            ->leftjoin('users as userd', 'userd.id', '=', 'comments.to_uid')
            ->leftjoin('task as tk', 'tk.id', '=', 'comments.task_id')
            ->orderBy($orderBy, $orderByType)
            ->paginate($page_size);
        $commentsArr = $comments->toArray();

        $view = [
            'data' => $commentsArr,
            'comment' => $comments,
            'merge' => $data,
        ];
        $this->theme->setTitle('互评记录');
        return $this->theme->scope('manage.commentList', $view)->render();
    }

    /**
     * 互评删除处理
     *
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function commentDel($id)
    {
        $attachment = CommentModel::where('id', $id)->first();
        if (!empty($attachment)) {
            $status = $attachment->delete();
            if ($status) {
                return redirect()->to('manage/getCommentList')->with(['message' => '操作成功']);
            }
        }
        return redirect()->to('manage/getCommentList')->with(['error' => '删除失败']);
    }

}
