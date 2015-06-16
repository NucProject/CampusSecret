
$class("QuestionItem", [kx.Weblet, kx.ActionMixin, kx.EventMixin],
{
    _templateFile: "questionitem.html",
    _onclickFlag: 'no',
    _question_id:null,

    __constructor: function(question) {
        this._question = question;
        this._actionBase = "admin.php?_url=/";
        this._question_id = this._question.question_id;


    },

    onCreated: function(domNode) {

        this._imageKey = this._question.image_key;
        var this_ = this;
        this.ajax('image/downloadUrl/' + this._imageKey, null, function(data){
            var d = eval("(" + data + ")");
            if (d.errorCode == 0) {
                this_.setImage(d.results['download-url']);
            }
        });
        domNode.find("div.content").text(this._question.question);
        domNode.find("a.delete-question").click(kx.bind(this, "deleteClicked"));
        domNode.click(kx.bind(this, this.onClick));
    },

    setImage: function(imageUrl) {
        this._domNode.find('img.picture').attr('src', imageUrl);
    },

    onClick: function() {
        this._domNode.siblings().css("background", "");
        this._domNode.css("background", "#FFFFAA");
    },

    deleteClicked: function() {
        var this_ = this;
        $(function(){
            if(confirm("是否确认删除")){
                this_.ajax("admin/delQuestion/" + this_._question_id, null, function(data) {
                    var d = eval("(" + data + ")");
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


$class("AddQuestionTabPane", [kx.Widget, kx.ActionMixin, kx.EventMixin],
{
    _schoolId: null,

    _academyId: null,

    _grade: null,

    __constructor: function() {
    },

    onAttach: function(domNode) {
        kx.activeWeb(domNode, null);
        domNode.find("a.add-question").click(kx.bind(this, "onPublish"));
        domNode.find("a.add-photo").click(kx.bind(this, "onAddPhoto"));
        domNode.find("a.admin-questions").click(kx.bind(this, "showPublishedQuestion"));
        this.initQiniu();
    },

    initQiniu: function() {

        var this_ = this;
        var uploader = Qiniu.uploader({
            runtimes: 'html5,flash,html4',    //上传模式,依次退化
            browse_button: 'pickphoto2',       //上传选择的点选按钮，**必需**
            uptoken_url: '/image/uptoken2',
            //Ajax请求upToken的Url，**强烈建议设置**（服务端提供）
            // uptoken : '<Your upload token>',
            //若未指定uptoken_url,则必须指定 uptoken ,uptoken由其他程序生成
            unique_names: true,
            // 默认 false，key为文件名。若开启该选项，SDK会为每个文件自动生成key（文件名）
            // save_key: true,
            // 默认 false。若在服务端生成uptoken的上传策略中指定了 `sava_key`，则开启，SDK在前端将不对key进行任何处理
            domain: 'http://qiniu-plupload.qiniudn.com/',
            bucket: "xiaoyuanmimi2",                         // 域名，下载资源时用到，**必需**
            //container: 'container',           //上传区域DOM ID，默认是browser_button的父元素，
            max_file_size: '100mb',             //最大文件体积限制
            //flash_swf_url: 'js/plupload/Moxie.swf',  //引入flash,相对路径
            max_retries: 3,                   //上传失败最大重试次数
            dragdrop: true,                   //开启可拖曳上传
            drop_element: 'container',        //拖曳上传区域元素的ID，拖曳文件或文件夹后可触发上传
            chunk_size: '4mb',                //分块上传时，每片的体积
            auto_start: true,                 //选择文件后自动上传，若关闭需要自己绑定事件触发上传
            init: {
                'FilesAdded': function(up, files) {
                    plupload.each(files, function(file) {
                        // 文件添加进队列后,处理相关的事情
                    });
                },
                'BeforeUpload': function(up, file) {
                    // 每个文件上传前,处理相关的事情
                },
                'UploadProgress': function(up, file) {
                    console.log(1);
                },
                'FileUploaded': function(up, file, info) {
                    // console.log(up);
                    // console.log(file);
                    var i = eval("(" + info + ")");
                    this_.imageKey = i.key;

                    if (!this_.imageKey)
                    {
                        alert('Bad Image-key');
                        return;
                    }

                    this_.ajax('image/downloadUrl/' + this_.imageKey, null, function(data){
                        var d = eval("(" + data + ")");
                        if (d.errorCode == 0) {
                            this_.setImage(d.results['download-url']);
                        }

                    });

                },
                'Error': function(up, err, errTip) {
                    //上传出错时,处理相关的事情
                },
                'UploadComplete': function() {
                    //队列文件处理完毕后,处理相关的事情
                },
                'Key': function(up, file) {
                    // 若想在前端对每个文件的key进行个性化处理，可以配置该函数
                    // 该配置必须要在 unique_names: false , save_key: false 时才生效
                    var key = "";
                    // do something with key here
                    return key
                }
            }
        });

    },

    setImage: function(imageUrl) {
        console.log(imageUrl)
        this._domNode.find('img.picture').attr('src', imageUrl);
    },

    onPublish: function() {
        if (!this.imageKey)
        {
            alert('添加提问前请上传图片')
            return;
        }
        var payload = {
            question: this.getContent(),
            image_key: this.imageKey
        }
        console.log(payload)
        this.ajax("admin/addQuestion", payload, function(data){
            console.log(data);
            alert("添加 成功");
        });

    },

    getContent: function() {
        return this._domNode.find("div.question-content").text();
    },

    onAddPhoto: function() {

    },

    showPublishedQuestion: function() {
        this.refresh("div.published-question-tab");
    },

    refresh: function(containerId) {
        this.ajax("admin/getQuestions/" , null , function(data) {
            var results = eval("(" + data + ")");
            console.log(results);
            var questions = results['results'];
            var questionList = this._domNode.find(containerId);
            questionList.empty();

            if (questions.length == 0)
            {
                questionList.append($("<div class='span12'><h3>无数据</h3></div>"));
                return;
            }
            var selected = true;
            for (var i=0;i<questions.length;i++) {
                this.addListEntry(questions[i], questionList, selected);
                selected = false;
            }
        });
    },

    addListEntry: function(entry, questionList, selected) {
        var questionItem = new QuestionItem(entry);
        questionItem.create(true).appendTo(questionList);
    }
});