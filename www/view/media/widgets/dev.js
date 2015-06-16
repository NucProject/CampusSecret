/**
 * Created by yuzhongmin on 14-6-11.
 */


$class("RedisTabPane", [kx.Widget, kx.ActionMixin, kx.EventMixin],
{
    __constructor: function() {

    }

});

String.prototype.base64Encode = function() {
    var str = this;
    //str = str.UTF16To8();
    var base64EncodeChars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

    var ret="", i=0, len;
    var charCode1, charCode2, charCode3;

    len = str.length;
    while (i < len) {
        charCode1 = str.charCodeAt(i++) & 0xff;
        if (i == len) {
            ret += base64EncodeChars.charAt(charCode1 >> 2);
            ret += base64EncodeChars.charAt((charCode1 & 0x3) << 4);
            ret += "==";
            break;
        }
        charCode2 = str.charCodeAt(i++);
        if (i == len) {
            ret += base64EncodeChars.charAt(charCode1 >> 2);
            ret += base64EncodeChars.charAt(((charCode1 & 0x3) << 4) | ((charCode2 & 0xF0) >> 4));
            ret += base64EncodeChars.charAt((charCode2 & 0xF) << 2);
            ret += "=";
            break;
        }
        charCode3 = str.charCodeAt(i++);
        ret += base64EncodeChars.charAt(charCode1 >> 2);
        ret += base64EncodeChars.charAt(((charCode1 & 0x3) << 4) | ((charCode2 & 0xF0) >> 4));
        ret += base64EncodeChars.charAt(((charCode2 & 0xF) << 2) | ((charCode3 & 0xC0) >> 6));
        ret += base64EncodeChars.charAt(charCode3 & 0x3F);
    }
    return ret;
}

$class("SessionsTabPane", [kx.Widget, kx.ActionMixin, kx.EventMixin],
{
    __constructor: function() {

    },

    signIn: function(username, password) {
        this.ajax("admin/signin", {"username":username, "password_md5": hex_md5(password) }, function(data){

            console.log("signin OK")
        });
    },

    onAttach: function(domNode) {


        domNode.find("a.session-count").bind("click", kx.bind(this, "onRefreshSession"));
    },

    onRefreshSession: function(){
        this.ajax("redis/sessions", null, function(data){

            console.log(data)
        });
    },

    addPolicy: function(platform, versionCode, target, url, force) {
        var f = force ? "force" : "option";
        this.ajax("admin/setVersionPolicy/" + platform,
            {
                version_code: versionCode,
                policy: f + ":" + "2.0" + ":" + url.base64Encode()
            },
            function(data){

            console.log(data)
        });
    }

});