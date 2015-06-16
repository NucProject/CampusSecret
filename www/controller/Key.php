<?php

class Key
{
    // -!-
    const NonUser = -1;

    // -!-
    const Deleted = 'deleted';
    // u:v:code:#{phone_number} => int
    // TTL
    const UserVerifyCode = 'u:v:code:';

    // u:v:ctrl:#{phone_number} => int
    // TTL
    const UserVerifyCtrl = 'u:v:ctrl:';

    // g:s:# = { school_id: school-name }
    const SchoolHash = 'g:s:#';

    // academy:# = { academy_id: academy-name }
    const AcademyHash = 'g:a:#';

    // s:q = array<question>
    const Questions = 's:q';

    // c:c:secret_id = array<comment>
    const Comments = 'c:c:';

    // s:s:# = {secret_id: object, }
    const SecretHash = 's:s:#';

    // s:c:# = {secret_id: count | 'deleted' }
    const SecretComments = 's:c:#';

    // s:s:school_id = array<int>
    const SecretStream = 's:s:';

    // s:rm:secret_id = { user_id ... }
    const SecretRemoved = 's:rm:';

    // s:lk:secret_id = { user_id ... }
    const SecretLiked = 's:lk:';

    // c:lk:comment_id = { user_id ... }
    const CommentLiked = 'c:lk:';

    // c:al1:#{secret_id} => array
    const CommentsAvatarList1 = 'c:al1:';

    // c:al2:#{secret_id} => array
    const CommentsAvatarList2 = 'c:al2:';

    // c:al2:#{secret_id} => array
    const CommentsAvatarList = 'c:al:';

    // count(secrets) +1
    const NoticeSecretLiked = 'n:s:lk:';

    // count(comments) +1
    const NoticeCommentLiked = 'n:c:lk:';

    // n:f:{user_id}  => { #{secret_id} => #floor }
    const NoticeSecretFloorHash = 'n:f:';

    // n:s:{secret_id}  = { user_id ... }
    const NoticeUserSecrets = 'n:s:';

    const NoticeSecretStore = 'n:a';

    const PushUserLastAccess = 'p:u:#';

    const PushAcademyGradeLatestTime = 'p:a:g:#';


    const EnvProduct = 'product';

    const EnvDev = 'dev';

    //r:s = array<int>
    const RecommendedSecrets = 'r:s';

    const iOSVerPolicy = 'vp:ios';

    const AndroidVerPolicy = 'vp:a';

    //push all user
    const pushAll = 'p:a';

    //'p:t1', 预留
    const pushType1 = 'p:t1';

    //'p:t2', "有人评论了你的秘密"
    const pushType2 = 'p:t2';

    //'p:t3', "你关注的秘密有了新评论"
    const pushType4 = 'p:t4';

    //'p:t3', push user with data "你的同学发了一个秘密"
    const pushType3 = 'p:t3';

    //群发push的哈希表名
    const groupPush = 'g:p';

    //单发push的哈希表名，'s:p:userId'
    const singlePush = 's:p:';

    const MarkSet = 'm:';

    const MarkSetDeleted = 'm:d:';


    const BatchMarkWork = 'bmw';

}
