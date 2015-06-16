/////////////////////////////////////////////////////////////////////////////////////////
// FGM

$require("Pagebar.js");

$class("ReportSecretItem", [kx.Weblet, kx.ActionMixin, kx.EventMixin],
{
    _templateFile: "reportsecretitem.html",

    _secret: null,

    _newsPreview: null,

    _newsPreviewShow: true,

    __constructor: function(secret) {
        this._secret = secret;
        this._actionBase = "admin.php?_url=/";
    },

    secretId: function() {
        return this._secret.secret_id;
    },

    onCreated: function(domNode) {

        domNode.find("div.summary .content").text(this._secret.content);
        domNode.find("span.time").text(this._secret.time);
        domNode.find("span.grade").text(this._secret.grade);
        domNode.find("span.academy").text(this._secret.academy_id);
        domNode.find("img.background_image ").attr( 'src', this._secret.background_image);

        domNode.find("a.delete").click(kx.bind(this, "deleteClicked"));
        domNode.click(kx.bind(this, this.onClick));

    },

    onClick: function() {
        this.fireEvent("select-secret");

        this._domNode.siblings().css("background", "");
        this._domNode.css("background", "#FFFFAA");
    },

    deleteClicked: function() {
        var this_ = this;
        this.ajax("admin/deleteSecret/" + this._secret.secret_id, null, function(data){
            var d = eval("(" + data + ")");
            if (d['results']['deleted'] == true)
            {
                this_.collapseAndHide();
            }
        });
    },

    collapseAndHide: function() {
        this._domNode.slideUp("fast");
    }
});

$class("ReportContentTabPane", [kx.Widget, kx.ActionMixin, kx.EventMixin],
{
    __constructor: function() {
        this._actionBase = "admin.php?_url=/";
    },

    onCreated: function(domNode) {

        domNode.find("div.summary .content").text(this._secret.content);
        domNode.find("span.time").text(this._secret.time);
        domNode.find("span.grade").text(this._secret.grade);
        domNode.find("span.academy").text(this._secret.academy_id);

        domNode.find("a.delete").click(kx.bind(this, "onClickDelete"));
        domNode.find("a.preview").click(kx.bind(this, "onClickPreview"));
        domNode.click(kx.bind(this, this.onClick));
    }
});

$class("ReportSecretsTabPane", ReportContentTabPane,
{
    _pageTime: null,

    _pageSchoolId: null,

    _pageReport: null,

    _allSchools: 'no',

    __constructor: function() {
        this._pageTime = {
            start:Date.today().toString('yyyy-MM-d'),
            end:Date.today().addHours(24).toString('yyyy-MM-d')
        }
    },

    onAttach: function(domNode) {
        domNode.find("ul.nav-tabs a").click(kx.bind(this, ""));
        kx.activeWeb(domNode, null);

        var w = Widget.widgetById("report-secrets-school-selector");
        this._pageReport = 0;
        w._domNode.find("select.grade").hide();
        w._domNode.find("select.academy").hide();
        this.bindEvent(w, "select-school-changed", "selectSchoolChanged");

        var this_ = this;
        $('body').bind("transfer-selected-time", function(event, time){
            this_.selectTimeChanged(null, null, time);
        })


        $("#allschool").click(function(){
            if($(this).attr("checked"))
            {
                this_._allSchools = 'yes';
                w._domNode.find("select.province").hide();
                w._domNode.find("select.schools").hide();
            }
            else
            {
                this_._allSchools = 'no';
                w._domNode.find("select.province").show();
                w._domNode.find("select.schools").show();
            }

            this_.refresh("div.secret-list");
        })

    },

    selectTimeChanged: function(e, sender, time) {
        this._pageTime = time;
        this.refresh("div.secret-list");
    },

    selectSchoolChanged: function(e, sender, schoolId) {
        this._pageSchoolId = schoolId;
        this.refresh("div.secret-list");
    },

    refresh: function(containerId) {
        var payload = {
            all_schools: this._allSchools,
            start: this._pageTime.start,
            end: this._pageTime.end,
            school_id: this._pageSchoolId,
            report: this._pageReport
        };

        this.ajax("admin/secrets/" , payload , function(data) {

            var results = eval("(" + data + ")");
            var secrets = results['results'];
            var secretList = this._domNode.find(containerId);
            secretList.empty();
            if (secrets.length == 0)
            {
                secretList.append($("<div class='span12'><h3>无数据</h3></div>"));
                return;
            }
            // First element for alignment
            secretList.append($("<div class='span12'></div>"));
            var selected = true;
            for (var i in secrets) {
                this.addListEntry(secrets[i], secretList, selected);
                selected = false;
            }
        });
    },

    addListEntry: function(entry, secretList, selected) {
        // console.log(secretList)
        var secretItem = new ReportSecretItem(entry);
        secretItem.create(true).appendTo(secretList);
        this.bindEvent(secretItem, "select-secret", "selectedSecretChanged");
        if (selected) {
            secretItem.onClick();
        }
    },

    selectedSecretChanged: function(event, sender) {
        //console.log(event);
        //console.log(sender);
    },

    tabSelectedChanged: function(event) {
        var target = $(event.delegateTarget);
        var tab = target.attr("tab");
        var containerIds = {"new": "#news-list", "pub": "#news-list-pub", "del": "#news-list-del"};
        this.refresh(tab, containerIds[tab])
    }

});

