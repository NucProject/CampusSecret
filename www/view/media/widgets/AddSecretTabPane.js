$require("Pagebar.js");
$class("SecretCreatedItem", [kx.Weblet, kx.ActionMixin, kx.EventMixin],
{
    _templateFile: "secretcreateditem.html",

    _secret: null,

    _newsPreview: null,

    _newsPreviewShow: true,

    _onclickFlag: 'no',


    __constructor: function(secret) {
        this._secret = secret;
        this._actionBase = "admin.php?_url=/";

    },

    onCreated: function(domNode) {
        this._secretkey = this._secret.secret_key;
        domNode.find("div.summary .content").text(this._secret.content);
        domNode.find("span.time").text(this._secret.time);
        domNode.find("span.secret_key").attr("secret_key", this._secret.secret_key);
        domNode.find("a.delete-secret-created").click(kx.bind(this, "deleteClicked"));
        domNode.click(kx.bind(this, this.onClick));


    },

    secretId: function() {
        return this._secret.secret_id;
    },

    onClick: function() {
        this._domNode.siblings().css("background", "");
        this._domNode.css("background", "#FFFFAA");
        if(this._onclickFlag == 'no'){
            this.showSecretSchools(this._secretkey);
            this._onclickFlag = 'yes';
        }
        else{
            this._domNode.find("span.secret_key").empty();
            this._onclickFlag = 'no';
        }
    },

    showSecretSchools: function(secretkey) {
        var payload = {
            secret_key: secretkey
        };
        var this_ = this;
        this.ajax("admin/secretKeySchools/", payload, function(data) {
            var results = eval("(" + data + ")");
            var schoolIds = results['results']['items'];
            for (var i = 0;i < schoolIds.length;i++) {
                this_._domNode.find("span.secret_key").append(schoolIds[i]['name'] + "&nbsp;&nbsp;");
            }
        });
    },



    deleteClicked: function() {
        var this_ = this;
        console.log(this._secretkey);
        $(function(){
                if(confirm("是否确认删除")){
                    this_.ajax("admin/deleteSecrets/" + this_._secretkey, null, function(data) {
                        var d = eval("(" + data + ")");
                        console.log(d);
                        if (d['results']['deleted'] == true)
                        {
                            this_.collapseAndHide();
                        }
                    });
                }
                else
                    return ;
        });
    },

    collapseAndHide: function() {
        this._domNode.slideUp("fast");
    }
});

$class("AddSecretTabPane", [kx.Widget, kx.ActionMixin, kx.EventMixin],
{
    _schoolId: null,

    _pageTime: null,

    _academyId: null,

    _grade: null,

    __constructor: function() {
        this._pageTime = {
            start:Date.today().toString('yyyy-MM-d'),
            end:Date.today().addHours(24).toString('yyyy-MM-d')
        }
    },

    isChinese: function(str){  //判断是不是中文
        var reCh=/[u00-uff]/;
        return !reCh.test(str);
    },

    contentChanged: function() {

        var strlen=0; //初始定义长度为0
        var txtval = $.trim(this.getContent());
        for(var i=0; i < txtval.length; i++){
            if(this.isChinese(txtval.charAt(i)) == true){
                strlen = strlen + 2; //中文为2个字符
            } else {
                strlen = strlen + 1; //英文一个字符
            }
        }
        strlen=Math.ceil(strlen/2);//中英文相加除2取整数
        var $num = 120;
        var $numName = 'num';
        var $par = this._domNode.find('div.box01-num');
        var $b = this._domNode.find('b.num');

        if($num-strlen<0){
            $par.html("超出 <b style='color:red;font-weight:lighter' class="+$numName+">"+Math.abs($num-strlen)+"</b> 字"); //超出的样式
        }
        else{
            $par.html("发言请尊守社区公约，还可以输入 <b class="+$numName+">"+($num-strlen)+"</b> 字"); //正常时候
        }
        $b.html($num-strlen);
    },

    onAttach: function(domNode) {
        kx.activeWeb(domNode, null);
        domNode.find("a.publish").click(kx.bind(this, "onPublish"));
        domNode.find("a.photo").click(kx.bind(this, "onAddPhoto"));
        domNode.find("a.adminSecrets").click(kx.bind(this, "showPublishedSecret"));
        var this_ = this;
        $('body').bind("transfer-selected-time", function(event, time){
            this_.selectTimeChanged(null, null, time);
        });
        setInterval(kx.bind(this, 'contentChanged'), 500);
    },

    selectTimeChanged: function(e, sender, time) {
        this._pageTime = time;

        var payload = {
            start: this._pageTime.start,
            end: this._pageTime.end
        };
        this.refresh(payload, "div.added_published_secrets_tab");
    },

    showPublishedSecret: function(){
        var payload = {
            start: this._pageTime.start,
            end: this._pageTime.end
        };
        this.refresh(payload, "div.added_published_secrets_tab");
    },


    refresh: function(payload, containerId) {
        this.ajax("admin/secretCreated/" , payload , function(data) {
            var results = eval("(" + data + ")");
            var secrets = results['results'];
            var secretList = this._domNode.find(containerId);
            secretList.empty();
            secretList.append($("<div class='span12'></div>"));
            if (secrets.length == 0)
            {
                secretList.append($("<div class='span12'><h3>无数据</h3></div>"));
                return;
            }
            var selected = true;
            for (var i=0;i<secrets.length;i++) {
                this.addListEntry(secrets[i], secretList, selected);
                selected = false;
            }
        });
    },

    addListEntry: function(entry, secretList, selected) {
        var secretItem = new SecretCreatedItem(entry);
        secretItem.create(true).appendTo(secretList);
    },

    onPublish: function() {

        var w = Widget.widgetById("add-secrets-school-selector");
        var secretKey = new Date().getTime();
        var schoolList = w.getSelectedSchools();

        if (schoolList == "check_all_schools")
        {
            this.ajax("admin/getAllSchools", null, function(data){
                schoolList = eval('(' + data + ')');

                this.separateAndPostArray(schoolList, secretKey);
            });
            return;
        }

        if(schoolList == '')
        {
            alert("没有选择学校");
            return ;
        }
     
        this.separateAndPostArray(schoolList, secretKey);
    },

    separateAndPostArray: function(schoolList, secretKey) {

        var start = 0;
        var end = 500;
        var j = 0;
        var x = this._domNode.find('.bar');
        x.css('width', "5%");

        for (var i = 0; i <= schoolList.length / 500; i += 1)
        {
            var schoolsPart = schoolList.slice(start, end);
            var payload = {
                "content": this.getContent(),
                "school_list": schoolsPart.join(','),
                "secret_key": secretKey
            };

            this.ajax("admin/postSecrets", payload, function(data){

                if(j == parseInt(schoolList.length / 500)){
                    x.css('width', "100%");
                    alert("发布完成");
                    x.css('width', "0%");
                    return;
                }
                var z=((j+1) / i) * 100;
                x.css('width', z+'%');
                j +=1;
            });

            start = end;
            end = start + 500;
        }
    },


    getContent: function() {
        return this._domNode.find("div.content").text();
    },

    onAddPhoto: function() {
        console.log("P");
    }
});

