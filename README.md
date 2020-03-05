### git 使用总结：


#Git add .  //添加缓存
#Git commit -m "提交说明"
#git push http://git.icinfo.net/yanjianxing/52pcsolution.git master //代码提交到分支

#git pull http://git.icinfo.net/yanjianxing/52pcsolution.git  //下拉github代码 会合并
#git clone http://git.icinfo.net/yanjianxing/52pcsolution.git //下拉远程仓库数据 多次执行会覆盖 适用第一次使用


### 创建分支：git branch dev
    +创建分支
    +刚创建的时候跟master分支一样
    
    
### 切换分支：git checkout dev
    +切换到指定分支
    + Git branch 可以查看有哪些分支
    
### 合并分支：git merge dev
    +合并分支内容


###测试提交


常用Git命令总结

git config --global user.name "你的名字" 让你全部的Git仓库绑定你的名字

git config --global user.email "你的邮箱" 让你全部的Git仓库绑定你的邮箱

git init 初始化你的仓库

git add . 把工作区的文件全部提交到暂存区

git add ./<file>/ 把工作区的<file>文件提交到暂存区

git commit -m "xxx" 把暂存区的所有文件提交到仓库区，暂存区空空荡荡

git remote add origin https://github.com/name/name_cangku.git 把本地仓库与远程仓库连接起来

git push -u origin master 把仓库区的主分支master提交到远程仓库里

git push -u origin <其他分支> 把其他分支提交到远程仓库

git status查看当前仓库的状态

git diff 查看文件修改的具体内容

git log 显示从最近到最远的提交历史

git clone + 仓库地址下载克隆文件

git reset --hard + 版本号 回溯版本，版本号在commit的时候与master跟随在一起

git reflog 显示命令历史

git checkout -- <file> 撤销命令，用版本库里的文件替换掉工作区的文件。我觉得就像是Git世界的ctrl + z

git rm 删除版本库的文件

git branch 查看当前所有分支

git branch <分支名字> 创建分支

git checkout <分支名字> 切换到分支

git merge <分支名字> 合并分支

git branch -d <分支名字> 删除分支,有可能会删除失败，因为Git会保护没有被合并的分支

git branch -D + <分支名字> 强行删除，丢弃没被合并的分支

git log --graph 查看分支合并图

git merge --no-ff <分支名字> 合并分支的时候禁用Fast forward模式,因为这个模式会丢失分支历史信息

git stash 当有其他任务插进来时，把当前工作现场“存储”起来,以后恢复后继续工作

git stash list 查看你刚刚“存放”起来的工作去哪里了

git stash apply 恢复却不删除stash内容

git stash drop 删除stash内容

git stash pop 恢复的同时把stash内容也删了

git remote 查看远程库的信息，会显示origin，远程仓库默认名称为origin

git remote -v 显示更详细的信息

git pull 把最新的提交从远程仓库中抓取下来，在本地合并,和git push相反

git rebase 把分叉的提交历史“整理”成一条直线，看上去更直观

git tag 查看所有标签，可以知道历史版本的tag

git tag <name> 打标签，默认为HEAD。比如git tag v1.0

git tag <tagName> <版本号> 把版本号打上标签，版本号就是commit时，跟在旁边的一串字母数字

git show <tagName> 查看标签信息

git tag -a <tagName> -m "<说明>" 创建带说明的标签。-a指定标签名，-m指定说明文字

git tag -d <tagName> 删除标签

git push origin <tagname> 推送某个标签到远程

git push origin --tags 一次性推送全部尚未推送到远程的本地标签

git push origin :refs/tags/<tagname> 删除远程标签<tagname>

git config --global color.ui true 让Git显示颜色，会让命令输出看起来更醒目

git add -f <file> 强制提交已忽略的的文件

git check-ignore -v <file> 检查为什么Git会忽略该文件

gitbranch other
