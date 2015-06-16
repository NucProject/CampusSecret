<?php

class Error
{
	const None = 0;

	const BadHttpMethod = 1;

	const BadRecord = 2;

	const BadCacheOperation = 3;

	const BadArguments = 4;

    const BadPayload = 5;

    const BadBinSearch = 10;

	const SecretNotFound = 101;

	const BadSession = 201;

    // Auth about [1000 - 1999]
	const RegisterFailedForPhoneNumberExists = 1001;

    const OperationFailedForInvalidVerifyCode = 1002;

    const RegisterFailedForBadUsernameOrPassword = 1003;

    const RegisterFailedForBadSchoolInfo = 1004;

    const SendVerifyCodeFailedForFrequency = 1005;

    const RegisterFailedForNotSendVerifyCode = 1006;

    const ResetPasswordFailedForPhoneNumberNotExists = 1007;

	const AuthFailed = 1012;

	const QuitFailed = 1013;

    const ChangeSchoolInfoOverTwice = 1020;

    // Secret about [2000 - 2999]
    const SecretPostInvalidContent = 2000;

	const FetchSecretsFailed = 2001;

    const DeleteSecretFailedForOwnership = 2002;

    const LikedSecretTwice = 2010;

    const FetchFailedForSecretDeleted = 2011;


	const FetchCommentsFailed = 3002;

    const DeleteCommentFailedForOwnership = 3002;

    const LikedCommentTwice = 3011;



    const NoticeFailedForUnexpectedBranch = 4001;
}
