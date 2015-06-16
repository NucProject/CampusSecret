/**
 * Created by yuzhongmin on 14-6-5.
 */

$class("AdminManagerPane", [kx.Widget, kx.ActionMixin, kx.EventMixin],
{
    __constructor: function() {

    },

    onAttach: function(domNode) {
        domNode.find("a.add-admin").click(kx.bind(this, "addAdmin"));
    },

    addAdmin: function() {
        var payload = {
            "username": this.getUsername(),
            "password_md5": this.passwordMD5()
        };
        this.ajax("admin/addAdmin", payload, function(){

        });
    },

    passwordMD5: function() {
        var p = this._domNode.find("input.password").val();
        return hex_md5(p);
    },

    getUsername: function() {
        return this._domNode.find("input.username").val();
    }


});