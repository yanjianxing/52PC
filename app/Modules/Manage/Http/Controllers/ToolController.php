<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\BasicController;
use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Modules\User\Model\AttachmentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ToolController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
        $this->theme->setTitle('附件管理');
    }

    /**
     * 附件管理列表
     *
     * @param Request $request
     * @return mixed
     */
    public function getAttachmentList(Request $request)
    {
        $list = AttachmentModel::select('id', 'disk', 'name', 'created_at');

        if ($request->get('id')){
            $list = $list->where('id', $request->get('id'));
        }
        if ($request->get('name')){
            $list = $list->where('name','like','%'. trim($request->get('name')) .'%');
        }

        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $list = $list->where('created_at','>',$start);
        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $list = $list->where('created_at','<',$end);
        }
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $by = $request->get('by') ? $request->get('by') : 'id';

        $list = $list->orderBy($by, $order)->paginate($paginate);
        $data = [
            'list' => $list
        ];
        $search = $request->all();
        $data['merge'] = $search;

        return $this->theme->scope('manage.attachmentlist', $data)->render();
    }

    /**
     * 附件删除处理
     *
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function attachmentDel($id)
    {
        $attachment = AttachmentModel::where('id', $id)->first();
        if (!empty($attachment)){
            $status = $attachment->delete();
            if ($status){
                if (file_exists($attachment->url)){
                    Storage::disk($attachment->disk)->delete($attachment->url);
                }
                return redirect()->to('manage/attachmentList')->with(['message' => '操作成功']);
            }
        }

    }

}
