/**
 * Created by yuzhongmin on 14-6-5.
 */

$require("Pagebar.js");

$class("AcademyItem", [kx.Weblet, kx.ActionMixin, kx.EventMixin],
{
    _templateFile: "academyitem.html",

    _academyId: null,

    _academy: null,

    _newsPreview: null,

    _newsPreviewShow: true,

    _deletedFlag: 'no',


    __constructor: function(academy) {
        this._academy = academy;
        this._actionBase = "admin.php?_url=/";

    },

    onCreated: function(domNode) {
        domNode.find("div.summary .content").text(this._academy.name);

        domNode.find("span.academy").text(this._academy.academy_id);

        this._academyId = this._academy.academy_id;

        domNode.click(kx.bind(this, this.onClick));
        domNode.find('a.delete').bind('click', kx.bind(this, 'deleteClicked'));
    },

    onClick: function() {
        this.fireEvent("select-secret");

        this._domNode.siblings().css("background", "");
        this._domNode.css("background", "#FFFFAA");
    },


    deleteClicked: function() {
        this.ajax("admin/deleteAcademy/" + this._academyId, null, function(data) {
            var d = eval("(" + data + ")");
            if (d['results']['deleted'] == true)
            {
                this.collapseAndHide();
            }
        });
    },

    collapseAndHide: function() {
        this._domNode.slideUp("fast");
    }
});

$class("AdminSchoolsPane", [kx.Widget, kx.ActionMixin, kx.EventMixin],
{
    currentSchoolId: null,
    _provinceId: 4,

    __constructor: function() {

    },

    onAttach: function(domNode) {
        kx.activeWeb(domNode, null);
        var w = Widget.widgetById("adminschools-secrets-school-selector");
        w._domNode.find("select.schools").hide()
        w._domNode.find("select.grade").hide();
        w._domNode.find("select.academy").hide();
        this.bindEvent(w, "select-province-changed", "selectProvinceChanged");
        domNode.find('a.search-school').bind('click', kx.bind(this, "onSearchSchool"));
        domNode.find('a.add-school').bind('click', kx.bind(this, "onAddNewSchool"));
        domNode.find('a.add-academy').bind('click', kx.bind(this, "onAddNewAcademy"));
        domNode.find('input.search-school').on('input', kx.bind(this, 'onSchoolNameChanged'));
        domNode.find('a.del-school').bind('click', kx.bind(this, "onDelSchool"));
    },

    onSchoolNameChanged: function() {
        this._domNode.find('a.add-school').css('display', 'none');
    },

    selectProvinceChanged: function(e, sender, provinceId) {
        this._provinceId = provinceId;
    },

    onSearchSchool: function() {
        var s = this._domNode.find('input.search-school').val();
        var schoolName = s.trim();
        this.ajax("school/searchSchool", {'name': schoolName}, function(data) {
            var content = eval("(" + data + ")");

            // TODO: List the school and academies, each of them can be removed by admin by clicked remove button.
            var academyList =  this._domNode.find('div.academylist');

            var found = content['results']['found'];
            if(found == false) {
                this.currentSchoolId = null;
                academyList.empty();
                this._domNode.find('a.del-school').css('display','none');
                academyList.append($("<div class='span12'><h3>该学校不存在</h3></div>"));
                this._domNode.find('a.add-school').css('display', '');
                return;
            }
            else{
                this._domNode.find('a.del-school').css('display','');
                academyList.empty();
                academyList.append($("<div class='span12'></div>"));
                var results = content['results'];
                var academies = results['academies'];
                this.currentSchoolId = results['school_id'];
                for (var i in academies) {
                    this.addListEntry(academies[i], academyList);
                }
            }
            });
    },

    addListEntry: function(entry, academyList) {
        var secretItem = new AcademyItem(entry);
        secretItem.create(true).appendTo(academyList);
    },

    onAddNewSchool: function() {

        var s = this._domNode.find('input.search-school').val();
        var schoolName = s.trim();
        var payload = {
            'name': schoolName,
            'province': this._provinceId
        };
        var this_ = this;
        this.ajax("admin/addSchool", payload, function(data) {
            var data = eval("(" + data +")");
            var results = data['results'];
            if(results['added'] == true){
                this_._domNode.find('a.add-school').css('display', 'none');
                alert("添加学校成功");
                this_.onSearchSchool();
            }
        });

    },

    onAddNewAcademy: function() {
        if (this.currentSchoolId == null){
            alert("请输入学校名称");
            return ;
        }
        var s = this._domNode.find('input.academy').val();
        var academyName = s.trim();
        var this_ = this;
        this.ajax("admin/addAcademy", {'name': academyName, 'school_id': this.currentSchoolId}, function(data) {
            this_.onSearchSchool();

        });
    },

    onDelSchool: function() {
        var s = this._domNode.find('input.search-school').val();
        var schoolName = s.trim();
        var this_ = this;
        if(confirm("将删除该学校以及所属学院，是否确认删除")){
            this.ajax("admin/delSchool/", {name: schoolName}, function(data){
                var data = eval("(" + data +")");
                var results = data['results'];
                if(results['del'] == true){
                    this_._domNode.find('a.del-school').css('display', 'none');
                    alert("删除学校成功");
                    this_._domNode.find('div.academylist').empty();
                    this_._domNode.find('input.search-school').val("");
                    this_._domNode.find('input.academy').val("");
                }
            });
        }
        else
            return ;
    }
})



