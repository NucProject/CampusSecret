
$class("AdminVersionTabPane", [kx.Widget, kx.ActionMixin, kx.EventMixin],
{
    __constructor: function() {
        this._actionBase = "admin.php?_url=/";
    },

    onAttach: function(domNode) {
        var versionList = domNode.find("div.versions-published-list");
        versionList.append($("<div class='span12'></div>"));

        this.ajax("admin/versions/"+ "android" , null, function(data) {

            data = eval("(" + data + ")");
            var versions = data['results'];

            for(var i in versions){
                this.addListEntry(versions[i], "div.versions-published-list");
            }

        });

        domNode.find("a.finish-version").click(kx.bind(this, "finishVersionAdmin"));
    },

    addListEntry: function(entry, versionList){
        var versionItem = new VersionItem(entry);
        versionItem.create(true).appendTo(versionList);
    },


    finishVersionAdmin: function() {
        var versions = this._domNode.find(".summary");
        var this_ = this;
        var policyArray = [];
        versions.each(function(){
            console.log($(this));
            var oldVersion = $(this).find('input.old_version').val();
            var newVersion = $(this).find('input.new_version').val();
            var download = $(this).find('input.down_load').val();
            var way = $(this).find('select.select_version_option').val();

            if(oldVersion == "" || newVersion == "" || download == "")
                return true;

            if (way >= 0)
            {
                var item = { version:oldVersion, policy: way + ':' + newVersion + ':' + download };
                policyArray.push(item)
            }

        });

        this_.ajax("admin/updateAndroidVersionPolicy" , { policy: policyArray }, function(data) {

            alert("提交成功");
            console.log(data);
        });
    }

});

$class("VersionItem", [kx.Weblet, kx.ActionMixin, kx.EventMixin],
{
    _templateFile: "versionitem.html",

    _version: null,

    __constructor: function(version) {
        this._version = version;
        this._actionBase = "admin.php?_url=/";
    },

    onCreated: function(domNode) {
        domNode.find("input.old_version").val(this._version.old_version);
        domNode.find("input.new_version").val(this._version.new_version);
        domNode.find("input.down_load").val(this._version.download);
        domNode.find("select.select_version_option").val(this._version.way);
    }
});
