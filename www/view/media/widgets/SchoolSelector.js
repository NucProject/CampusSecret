/**
 * Created by yuzhongmin on 14-6-5.
 */
provinceStr = '([{"0": "全部"}, {"4": "北京"} , {"1": "上海"} , {"8": "天津"} ,  {"30": "重庆"} , {"13": "广东"} , {"22": "湖北"} , {"31": "陕西"} ,{"16": "江苏"} , {"20": "浙江"} ,{"23": "湖南"} , {"6": "吉林"} ,{"7": "四川"} , {"10": "安徽"} , {"11": "山东"} , {"12": "山西"} , {"14": "广西"} , {"17": "江西"} ,{"18": "河北"} , {"19": "河南"} , {"21": "海南"} ,  {"25": "甘肃"} , {"26": "福建"} , {"2": "云南"} , {"3": "内蒙古"} , {"9": "宁夏"} , {"28": "贵州"} , {"15": "新疆"} ,{"27": "西藏"} , {"29": "辽宁"} , {"32": "青海"} , {"34": "黑龙江"} ,{"33": "香港"} , {"24": "澳门"} , {"5": "台湾"}])';

$class("SchoolSelector", [kx.Widget, kx.ActionMixin, kx.EventMixin],
{
    templateString: "<select class='province'></select>&nbsp;&nbsp;&nbsp;<select class='schools'></select>&nbsp;&nbsp;&nbsp;<select class='academy'></select>&nbsp;&nbsp;&nbsp;<select class='grade'></select>",

    __constructor: function() {

    },

    onAttach: function(domNode) {
        domNode.append(this.templateString);

        var provinces = eval(provinceStr);
        var provinceOptions = domNode.find("select.province");

        for (var i in provinces)
        {
            var p = provinces[i];
            var v = 0;
            var name = "";
            for (var s in p)
            {
                v = s;
                name = p[s];
            }

            if (v == 0)
                continue;

            var html = "<option value='" + v + "'>" + name +"</option>";
            provinceOptions.append($(html));
        }

        var gradeOptions = domNode.find("select.grade");
        for (var i = 2006; i < 2018; i++)
        {
            gradeOptions.append($("<option value='" + i + "'>" + i +"</option>"));
        }

        domNode.find("select.province").change(kx.bind(this, "changeProvince"));
        domNode.find("select.schools").change(kx.bind(this, "schoolChanged"));
        domNode.find("select.academy").change(kx.bind(this, "academyChanged"));
        domNode.find("select.grade").change(kx.bind(this, "gradeChanged"));

        this.changeProvince(4);
    },

    changeProvince: function(province) {
        var v = this._domNode.find("select.province").val() || province;
        if (v == 0)
            return;
        this.ajax("school/list/" + v, null, function(data) {
            var results = eval("(" + data + ")");
            if (results['errorCode'] != 0) {
                console.log("Error");
                return;
            }
            var schools = eval("(" + data + ")")['results'];
            var schoolOptions = $("select.schools");
            schoolOptions.empty();

            var firstSchoolId = schools[0]['school_id'];
            for (var i in schools)
            {
                var s = schools[i];
                var html = "<option value='" + s['school_id'] + "'>" +s.name +"</option>";
                schoolOptions.append($(html));

            }
            this.fireEvent("select-province-changed", v);
            this.schoolChanged(null, firstSchoolId);
        });
    },

    schoolChanged: function(e, firstSchoolId) {
        var schoolId = firstSchoolId || this._domNode.find("select.schools").val();

        this.ajax("school/academy/" + schoolId, null, function(data) {
            var results = eval("(" + data + ")");
            if (results['errorCode'] != 0) {
                console.log("Error");
                return;
            }
            var academies = eval("(" + data + ")")['results'];
            var academyOptions = $("select.academy");
            academyOptions.empty();

            var firstAcademyId = academies[0]['academy_id'];
            for (var i in academies)
            {
                var s = academies[i];
                var html = "<option value='" + s['academy_id'] + "'>" +s.name +"</option>";
                academyOptions.append($(html));
            }

            this.academyChanged(null, firstAcademyId);
        });

        this.fireEvent("select-school-changed", schoolId);
    },

    academyChanged: function(e, firstAcademyId) {
        var academyId = firstAcademyId || this._domNode.find("select.academy").val();
        this.fireEvent("select-academy-changed", academyId);
    },

    gradeChanged: function(e) {
        var gradeOptions = this._domNode.find("select.grade");
        this.fireEvent("select-grade-changed", gradeOptions.val());
    },

    getSchoolId: function() {
        return this._domNode.find("select.schools").val();
    },

    getAcademyId: function() {
        return this._domNode.find("select.academy").val();
    },

    getGrade: function() {
        return this._domNode.find("select.grade").val();
    }

});

$class("SchoolsPane", [kx.Weblet, kx.ActionMixin, kx.EventMixin],
{
    _provinceId: null,

    _provinceName: null,

    _templateFile: 'schoolspane.html',

    _checkLabel: '<label class="checkbox" style="float: left;margin-left: 10px"><div class="checker"><span class="checked"><input type="checkbox"></span></div><span class="name"></span></label>',

    __constructor: function(provinceId, provinceName) {

    },

    onCreated: function(domNode, args) {

        this._provinceId = args[0];
        this._provinceName = args[1];

        console.log(domNode)

        domNode.find('.caption span.title').text(this._provinceName);

        this.ajax("school/list/" + this._provinceId, null, function(data) {
            var results = eval("(" + data + ")");
            if (results['errorCode'] != 0) {
                console.log("Error");
                return;
            }
            var schools = eval("(" + data + ")")['results'];
            var schoolOptions = domNode.find('.portlet-body div.list');
            console.log(schoolOptions)
            schoolOptions.empty();

            for (var i in schools)
            {
                var s = schools[i];
                // var html = "<option value='" + s['school_id'] + "'>" +s.name +"</option>";

                var checkNode = $(this._checkLabel);
                checkNode.find('span.name').text(s.name);
                checkNode.find('div.checker').attr('value', s['school_id']);
                schoolOptions.append(checkNode);
            }

            schoolOptions.find("input").bind('click', function() {
                var n = $(this).parent();
                n.toggleClass('checked');
            });

            domNode.find("label.select-all input").bind('click', function() {
                var n = $(this).parent();
                n.toggleClass('checked');
                var checked = n.hasClass('checked');
                if (checked)
                {
                    schoolOptions.find('div.checker span').addClass('checked');
                }
                else
                {
                    schoolOptions.find('div.checker span').removeClass('checked');
                }
            })

        });
    }
});

///////////////////////////////////////////////////////////////////////////////
// SchoolSelectorEx!!
$class("SchoolSelectorEx", [kx.Widget, kx.ActionMixin, kx.EventMixin],
{
    templateString: "<div class='province'></div><div style='clear:both'><div class='schools'></div>",

    _checkLabel: '<label class="checkbox" style="float: left;margin-left: 10px"><div class="checker"><span class="h"><input type="checkbox" value=""></span></div><span class="name"></span></label>',

    __constructor: function() {

    },

    onAttach: function(domNode) {
        var self = this;
        domNode.append(this.templateString);

        var provinces = eval(provinceStr);
        var provinceOptions = domNode.find("div.province");
        for (var i in provinces)
        {
            var p = provinces[i];
            var v = 0;
            var name = "";
            for (var s in p)
            {
                v = s;
                name = p[s];
            }

            var checkNode = $(this._checkLabel);
            checkNode.find('span.name').text(name);
            checkNode.find('div.checker').attr('value', v);
            if (v == 0)
            {
                checkNode.addClass('all-schools');
            }
            provinceOptions.append(checkNode);
        }

        var gradeOptions = domNode.find("select.grade");
        for (var i = 2006; i < 2018; i++)
        {
            gradeOptions.append($("<option value='" + i + "'>" + i +"</option>"));
        }

        domNode.find("div.province input").bind('click', function() {
            var n = $(this).parent();

            var node = n.parent().parent();
            var provinceName = node.text();

            if (n.parent().attr('value') == 0)
            {
                n.toggleClass('checked');
                // Mark all the province, and NOT expand all the schools:)
                var checked = n.hasClass('checked')
                if (checked)
                {
                    n.parent().parent().siblings().find('span.h').addClass('checked');
                    if (self.__array)
                    {
                        for (var i in self.__array)
                        {
                            self.__array[i].hide();
                        }
                    }
                }
                else
                {
                    n.parent().parent().siblings().find('span.h').removeClass('checked');
                }
            }
            else
            {
                var all = domNode.find('div.province .all-schools span.h');
                if (!all.hasClass('checked'))
                {
                    n.toggleClass('checked');
                    self.onCheckProvince(n.parent(), n.hasClass('checked'), provinceName);
                }
            }
        });

        domNode.find("select.academy").change(kx.bind(this, "academyChanged"));
        domNode.find("select.grade").change(kx.bind(this, "gradeChanged"));
    },

    onCheckProvince: function(checker, checked, provinceName) {

        this.__array = this.__array || [];

        var provinceId = checker.attr('value');
        var w = this.__array[provinceId];
        if (w == null || w == undefined)
        {
            var schoolsPane = new SchoolsPane();
            var domNode = schoolsPane.create(provinceId, provinceName);
            domNode.appendTo(this._domNode.find('div.schools'));    //显示该省所有学校

            this.__array[provinceId] = w = schoolsPane;
        }

        if (checked)
        {
            w.hide(false);
        }
        else
        {
            w.hide(true);
        }
    },

    getSelectedSchools: function() {

        if (this._domNode.find('label.all-schools span.h').hasClass('checked'))
        {
            return "check_all_schools";
        }

        var portlet = this._domNode.find('div.schools div.portlet');
        var c = [];

        portlet.each(function(){
            if ($(this).css('display') != 'none')
            {
                $(this).find('div.checker span.checked').each(function(){
                        var schoolId = $(this).parent().attr('value');
                        if(schoolId==undefined){
                            return true;
                        }
                        c.push(schoolId);
                });
            }

        });
        return c;
    }

});