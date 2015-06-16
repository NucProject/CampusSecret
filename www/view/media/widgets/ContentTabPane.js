/////////////////////////////////////////////////////////////////////////////////////////
// Author: Healer

$require("Pagebar.js");

$class("SecretItem", [kx.Weblet, kx.ActionMixin, kx.EventMixin],
{
	_templateFile: "secretitem.html",

	_secret: null,

	_newsPreview: null,

	_newsPreviewShow: true,

    _deletedFlag: 'no',


	__constructor: function(secret) {
		this._secret = secret;
		this._actionBase = "admin.php?_url=/";

	},

    setdeletedflag: function(deletedFlag){
        this._deletedFlag = deletedFlag;
    },


    secretId: function() {
        return this._secret.secret_id;
    },


	onCreated: function(domNode) {
        domNode.find("div.summary .content").text(this._secret.content);
        domNode.find("span.time").text(this._secret.time);
        var info = this._secret.school + " " + this._secret.academy + " " + this._secret.grade;
        domNode.find("span.school").text(info);
        domNode.find("img.background_image ").attr( 'src', this._secret.background_image);
        domNode.click(kx.bind(this, this.onClick));

        if(this._deletedFlag == 'yes'){
            //console.log(this._deletedFlag,"$$$$")
            domNode.find("a.delete").css('display', 'none');
            domNode.find("a.weibo").css('display', 'none');
            domNode.find("a.recommend").css('display', 'none');

        }else{
            domNode.find("a.delete").click(kx.bind(this, "deleteClicked"));
            domNode.find("a.weibo").click(kx.bind(this, "sharedToWeibo"));
            domNode.find("a.recommend").click(kx.bind(this, "recommendSecret"));
        }
	},

	onClick: function() {
		this.fireEvent("select-secret");

        this._domNode.siblings().css("background", "");
        this._domNode.css("background", "#FFFFAA");
	},

    sharedToWeibo: function() {

	},

    recommendSecret: function() {

        if(!this.schoolselector) {
            this.schoolselector = new SchoolSelectorEx();
            this.schoolselector.attach(this._domNode.find('div.schoolselector'));
        }

        var recommendButton = this._domNode.find("a.recommend");
        if (recommendButton.hasClass('ready'))
        {
            var schoolList = this.schoolselector.getSelectedSchools();
            if (schoolList.length > 0) {
                var schoolList = schoolList.join(',');
                var payload = {
                    secret_id: this._secret.secret_id,
                    school_list: schoolList
                };
                this.ajax("admin/recommendSecret" , payload, function(data) {
                    var d = eval("(" + data + ")");
                    if (d['errorCode'] == 0)
                    {
                        // TODO: use friendly style to show the results;
                        alert(d['results']);
                    }
                });
            }
            this.schoolselector._domNode.slideUp("fast");
            recommendButton.text('推荐');
        }
        else
        {
            this.schoolselector._domNode.slideDown("fast");
            recommendButton.text('确认推荐');
        }

        recommendButton.toggleClass('ready');
    },

    deleteClicked: function() {
        var this_ = this;
        this.ajax("admin/deleteSecret/" + this._secret.secret_id, null, function(data) {
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

$class("ContentTabPane", [kx.Widget, kx.ActionMixin, kx.EventMixin], 
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

$class("SecretsTabPane", ContentTabPane,
{
    _pageTime: null,

    _pageSchoolId: null,

    _deletedFlag: null,

	__constructor: function() {
        this._pageTime = {
            start:Date.today().toString('yyyy-MM-d'),
            end:Date.today().addHours(24).toString('yyyy-MM-d')
        }
	},

	onAttach: function(domNode) {
		//domNode.find("ul.nav-tabs a").click(kx.bind(this, "dddd"));
        kx.activeWeb(domNode, null);
        var w = Widget.widgetById("secrets-school-selector");
        w._domNode.find("select.grade").hide();
        w._domNode.find("select.academy").hide();
        this.bindEvent(w, "select-school-changed", "selectSchoolChanged");
        var this_ = this;
        $('body').bind("transfer-selected-time", function(event, time){
            this_.selectTimeChanged(null, null, time);
        });

        domNode.find("a.published").click(kx.bind(this, "onShowPublishedSecret"));
        domNode.find("a.deleted2").click(kx.bind(this, "onShowDeletedSecret"));

	},

    onShowPublishedSecret: function() {
        this._deletedFlag = 'no';
        this.selectPublishedChanged();
    },

    onShowDeletedSecret: function() {
        this._deletedFlag = 'yes';
        this.selectDeletedChanged();
    },

    selectTimeChanged: function(e, sender, time) {
        this._pageTime = time;

        var payload = {
            start: this._pageTime.start,
            end: this._pageTime.end,
            school_id: this._pageSchoolId,
            deleted:this._deletedFlag
        };
        if(this._deletedFlag == 'yes'){
            console.log("c1",this._pageTime);
            this.refresh(payload, "div.secret-list-del");
        }else
            this.refresh(payload, "div.secret-list");
    },

    selectSchoolChanged: function(e, sender, schoolId) {
        this._pageSchoolId = schoolId;
        var payload = {
            start: this._pageTime.start,
            end: this._pageTime.end,
            school_id: this._pageSchoolId,
            deleted:this._deletedFlag

        };
        if(this._deletedFlag == 'yes'){
            this.refresh(payload, "div.secret-list-del");
        }else
            this.refresh(payload, "div.secret-list");
    },

    selectPublishedChanged: function() {
        var payload = {
            start: this._pageTime.start,
            end: this._pageTime.end,
            school_id: this._pageSchoolId,
            deleted:this._deletedFlag
        };
        this.refresh(payload, "div.secret-list");
    },

    selectDeletedChanged: function() {
        var payload = {
            start: this._pageTime.start,
            end: this._pageTime.end,
            school_id: this._pageSchoolId,
            deleted:this._deletedFlag
        };
        this.refresh(payload, "div.secret-list-del");
    },

    // TODO: refactor ....
    refreshList: function(time, schoolId, deleted, containerId) {
        var payload = {
            start: time.start,
            end: time.end,
            school_id: schoolId,
            deleted: deleted
        };
        this.refresh(payload, containerId);
    },

	refresh: function(payload, containerId) {
        // console.log(payload);   // Why no data retrieved?
		this.ajax("admin/secrets/" , payload , function(data) {
			var results = eval("(" + data + ")");
            console.log(results);   // Why no data retrieved?
            var secrets = results['results'];
			var secretList = this._domNode.find(containerId);


            secretList.empty();
			// First element for alignment
            secretList.append($("<div class='span12'></div>"));

            if (secrets.length == 0)
            {
                secretList.append($("<div class='span12'><h3>无数据</h3></div>"));
                return;
            }
			var selected = true;
			for (var i in secrets) {

				this.addListEntry(secrets[i], this._deletedFlag, secretList, selected);

				selected = false;
			}
		});
	},

	addListEntry: function(entry, deletedFlag, secretList, selected) {
        // console.log(secretList)
		var secretItem = new SecretItem(entry);


        secretItem.setdeletedflag(deletedFlag);
        secretItem.create(true).appendTo(secretList);

		this.bindEvent(secretItem, "select-secret", "selectedSecretChanged");
		if (selected) {
            secretItem.onClick();
		}
	},

    selectedSecretChanged: function(event, sender) {
	   /* console.log("#####");
        console.log("*****");*/
    },

	tabSelectedChanged: function(event) {
		var target = $(event.delegateTarget);
		var tab = target.attr("tab");
		var containerIds = {"new": "#news-list", "pub": "#news-list-pub", "del": "#news-list-del"};
		this.refresh(tab, containerIds[tab])
	}

});

$class("CommentItem", [kx.Weblet, kx.ActionMixin, kx.EventMixin],
{
    _templateFile: "commentitem.html",

    _comment: null,

    __constructor: function(secretId, comment) {
        this._secretId = secretId;
        this._comment = comment;
    },

    onCreated: function(domNode) {

        domNode.find("div.summary .content").text(this._comment.content);
        domNode.find("span.time").text(this._comment.time);
        domNode.find("span.grade").text(this._comment.grade);
        domNode.find("span.academy").text(this._comment.academy_id);

        domNode.find("a.delete").click(kx.bind(this, "deleteClicked"));
    },

    deleteClicked: function() {
        var this_ = this;
        this.ajax("admin/deleteComment/" + this._secretId + '/' + this._comment.comment_id, null, function(data){
            var results = eval("(" + data + ")");
            console.log(results);
            if (results['results']['deleted'] == true)
            {
                this_.collapseAndHide();
            }
        });


    },

    collapseAndHide: function() {
        this._domNode.slideUp("fast");
    }
});


$class("CommentsTabPane", ContentTabPane,
{
    _secretItem: null,


	__constructor: function() {
	},

	onAttach: function(domNode) {
		this.bindEvent(Widget.widgetById("secrets-tab-pane"), "select-secret", "selectedSecretChanged");
	},

    selectedSecretChanged: function(e, sender) {
		this._secretItem = sender;
        var secretId = this._secretItem.secretId();
        this.ajax("admin/comments/" + secretId , null, function(data) {
            var results = eval("(" + data + ")");
            var comments = results['results'];
            var commentsList = this._domNode.find("#comments_tab");

            commentsList.empty();
            // First element for alignment
            commentsList.append($("<div class='span12'></div>"));
            var selected = true;

            if (comments.length > 0) {
                for (var i in comments) {
                    this.addListEntry(secretId, comments[i], commentsList, selected);
                    selected = false;
                }
            } else {
                commentsList.append($("<span class='span12'>没有评论</span>"));
            }
        });
	},

    addListEntry: function(secretId, comment, commentsList, selected) {
        var commentItem = new CommentItem(secretId, comment);
        commentItem.create().appendTo(commentsList);
    }

});
