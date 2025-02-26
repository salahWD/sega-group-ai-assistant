import { SaBox } from "@sabox";

$.ajax({
  url: window.location.href + `/store`,
  method: "GET",
}).done((response) => {
  let res = JSON.parse(response);

  new SaBox("#mycustomchat", {
    storeURL: window.location.href + "/store", // store messages end-point
    position: 2, // 1 => fixed | 2 => static
    apiEndPoint: window.location.href + "/gemini",
    // apiEndPoint: window.location.href + "/chatGPT",
    messages: res || [],
  });
});
