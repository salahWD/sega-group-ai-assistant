import { GoogleGenerativeAI } from "@google/generative-ai";
import "jquery";

export class SaBox {
  constructor(element, options) {
    this.target = $(element);
    this.avatar = "https://placehold.co/60x60/333/white?text=sa";
    this.eventsList = [];

    if (options.avatar) {
      this.avatar = options.avatar;
    }
    if (options.storeURL) {
      this.storeURL = options.storeURL;
    }
    if (options.apiEndPoint) {
      this.apiEndPoint = options.apiEndPoint;
    }

    this.chat = {
      messages: options.messages ?? [],
    };

    saBoxStructureCreation(this, options);

    let history = [];

    if (this.chat && this.chat.messages && this.chat.messages.length > 0) {
      this.chat.messages.forEach((msg) => {
        this.chatMessage(msg);
        history.push({
          role: msg.user == 1 ? "user" : "model",
          parts: [{ text: msg.text }], // Add the message as a "parts" array
        });
      });
    }
  }

  async addMessage(message) {
    this.fireEvent("message", message.user == 1 ? 1 : 2, message.text);

    // uiانشاء الرسالة في ال
    let el = this.chatMessage(message);

    if (this.chat) {
      // للرسالة htmlتعيين عنصر ال
      message.element = el;
      // إدخال الرسالة في مصفوفة الرسائل للشات
      this.chat?.messages?.push(message);
      // حفظ الرسالة في قاعدة البيانات
      if (message.user == 1) {
        this.fireEvent("message:sent", el, message.text);
      } else {
        this.fireEvent("message:received", el, message.text);
      }
      if (this.storeURL) {
        this.storeMessage(message);
      }
    }

    if (message.user == 1 && this.apiEndPoint) {
      // كان المرسل هو المستخدم (أنا)
      // apiسيرسل الرسالة وينتظر الجواب من ال

      // End Pointارسال الرسالة للجيميناي بإستخدام ال
      const response = $.ajax({
        url: this.apiEndPoint,
        method: "POST",
        contentType: "application/json",
        data: JSON.stringify({ message: message.text }),
      })
        // تلقي الجواب
        .then((response) => {
          // إضافة الرسالة الى مصفوفة الرسائل و الشات
          this.addMessage({
            user: 2,
            text: response.text,
          });
        });
    }
  }

  chatMessage(message) {
    // Function to decode HTML entities
    const decodeEntities = (str) => {
      const textArea = document.createElement("textarea");
      textArea.innerHTML = str;
      return textArea.value;
    };

    // Decode the message content
    message.text = decodeEntities(message.text);

    let messageEL;

    if (message.user == 1) {
      messageEL = $("<div/>", {
        class: "my_msg_holder",
        css: {
          display: "flex",
          flexDirection: "row-reverse",
          alignItems: "flex-end",
        },
      }).appendTo(this.chatBody);

      $("<div/>", {
        class: "chat_msg chat_msg_me",
        text: message.text,
      }).appendTo(messageEL);

      $("<span/>", {
        class: "msg-status",
        css: {
          float: "none",
          marginBottom: "0",
        },
        text: message.status ? message.status : "🔵",
      }).appendTo(messageEL);
      if (this.messageInput != null) {
        $(this.messageInput).val("");
      }
    } else if (message.user == 2) {
      messageEL = $("<div/>", {
        class: "chat_msg chat_msg_user",
        css: {
          float: "none",
        },
        text: message.text,
      }).appendTo(this.chatBody);

      let avatar = $("<div/>", { class: "chat_avatar" }).appendTo(messageEL);
      $("<img/>", { src: this.avatar }).appendTo(avatar);

      if (this.chat.messages) {
        for (let i = this.chat.messages.length; i--; i > 0) {
          if (this.chat.messages[i].user == 1) {
            if (this.chat.messages[i].status == "🟡") {
              // edit the status of the message object in chat array
              this.chat.messages[i].status = "🔵";
              // edit the actual status element in the DOM
              $(this.chat.messages[i].element).find(".msg-status").text("🔵");
            } else {
              break; // when he find the bot's last message no need to continue
            }
          } else {
            break; // if the last message before this one is from the bot no need to continue
          }
        }
      }
    } else {
      messageEL = $("<div/>", {
        class: "chat_msg chat_msg_chat",
        text: message.text,
      }).appendTo(this.chatBody);
    }

    this.chatBody.scrollTop(this.chatBody[0].scrollHeight);

    return messageEL;
  }

  storeMessage(message) {
    var settings = {
      url: this.storeURL,
      method: "POST",
      data: {
        message: JSON.stringify({ user: message.user, text: message.text }),
      },
    };

    $.ajax(settings)
      .done((response) => {
        let res = JSON.parse(response);
        if (!res) {
          console.error("this message did not stored:", res);
          $(message.element).find(".msg-status").text("🔴");
        }
      })
      .fail(function (xhr, status, error) {
        console.error("this message did not stored:", error);
        $(message.element).find(".msg-status").text("🔴");
      });
  }

  send({ user, text, status = "🔵" } = message) {
    this.addMessage({ user, text, status });
    console.log(user);
    console.log(text);
  }

  on(event, callback) {
    this.eventsList.push({ event, callback });
  }

  fireEvent(event, ...params) {
    if (this.eventsList.length) {
      this.eventsList.forEach((ev) => {
        if (ev.event == event) {
          ev.callback(...params);
        }
      });
    }
  }
}

function saBoxStructureCreation(instance, options) {
  let settings = $.extend(
    {
      position: 1, // 1 => fixed, 2 => static
      header: {
        title: "Username Or Title",
        subtitle: "Customer",
        status: "🔴",
      },
      chat: {
        messages: [],
      },
    },
    options
  );

  if (settings.position == 2) {
    instance.target.css({ position: "relative" });
  }

  let saboxEl;
  if (settings.position != 2) {
    saboxEl = $("<div/>", {
      class: settings.position != 2 ? "fabs position-fixed" : "fabs",
    }).appendTo(instance.target);
  }

  let MainBody = $("<div/>", {
    class: settings.position != 2 ? "chat position-fixed" : "chat chat-static",
    style:
      settings.position == 2 ? "opacity: 1;position: relative;inset:0" : "",
  }).appendTo(settings.position == 2 ? instance.target : saboxEl);

  let header = $("<div/>", {
    class: "chat_header",
  }).appendTo(MainBody);

  let headerOptions = $("<div/>", {
    class: "chat_option",
    css: {
      paddingRight: "15px",
      paddingLeft: "15px",
    },
  }).appendTo(header);

  $("<h5/>", {
    class: "title",
    css: {
      margin: 0,
      fontSize: "13px",
      fontFamily: "Roboto",
      fontWeight: "500",
      color: "#f3f3f3",
    },
    text: settings.header.title,
  }).appendTo(headerOptions);

  $("<span/>", {
    class: "header-subtitle",
    text: settings.header.subtitle,
  }).appendTo(headerOptions);

  $("<span/>", {
    class: "header-status",
    css: {
      color: " #dd5050",
      fontSize: "11px",
      fontWeight: "400",
    },
    text: settings.header.status,
  }).appendTo(headerOptions);

  let chatBody = $("<div/>", {
    class:
      settings.position == 1
        ? "chat_box chat_body"
        : "chat_box chat_body d-block",
  }).appendTo(MainBody);
  instance.chatBody = chatBody;

  let footer = $("<div/>", {
    class: "sabox_field",
  }).appendTo(MainBody);

  let footerForm = $("<form/>").appendTo(footer);
  instance.footerForm = footerForm;

  let uploadImageBtn = $("<button/>", { class: "fab is-visible" }).appendTo(
    footerForm
  );
  $("<i/>", { class: "zmdi zmdi-camera" }).appendTo(uploadImageBtn);

  let messageInput = $("<input/>", {
    placeholder: "Send a message",
    class: "chat_field chat_message",
  }).appendTo(footerForm);
  instance.messageInput = messageInput;

  let sendMsgBtn = $("<button/>", {
    type: "submit",
    class: "fab is-visible",
  }).appendTo(footerForm);
  $("<i/>", { class: "zmdi zmdi-mail-send" }).appendTo(sendMsgBtn);

  if (settings.position != 2) {
    let saboxMainBtn = $("<button/>", {
      class: "saboxBtn",
    })
      .appendTo(saboxEl)
      .on("click", function () {
        $(instance.target)
          .find(".mainbtnicon")
          .toggleClass("zmdi-comment-outline");
        $(instance.target).find(".mainbtnicon").toggleClass("zmdi-close");
        $(instance.target).find(".mainbtnicon").toggleClass("is-active");
        $(instance.target).find(".mainbtnicon").toggleClass("is-visible");
        $(instance.target).find(".chat").toggleClass("is-visible");
        $(instance.target).find(".fab").toggleClass("is-visible");
      });

    instance.mainBtn = saboxMainBtn;

    $("<i/>", {
      class: "mainbtnicon prime zmdi zmdi-comment-outline",
    }).appendTo(saboxMainBtn);

    $(instance.mainBtn).on("click", function () {
      $(instance).find(".prime").toggleClass("zmdi-comment-outline");
      $(instance).find(".prime").toggleClass("zmdi-close");
      $(instance).find(".prime").toggleClass("is-active");
      $(instance).find(".prime").toggleClass("is-visible");
      $(instance).find(".chat").toggleClass("is-visible");
      $(instance).find(".fab").toggleClass("is-visible");
    });
  }

  $(instance.footerForm).on("submit", function (e) {
    e.preventDefault();
    let newMsg = $(instance.messageInput).val();

    if (newMsg && newMsg != "") {
      instance.addMessage({ user: 1, text: newMsg, status: "🟡" });
    }
  });
}
