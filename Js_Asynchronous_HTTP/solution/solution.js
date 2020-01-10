/**
 * Data model for loading the work hour categories and fileld hours.
 * The model implements internal cache, so the data does not have to be
 * loaded every time from the REST API.
 */
class DataModel {

  /**
   * Initialize the data model with given URL pointing to REST API.
   * @param {string} apiUrl Api URL prefix (without the query part).
   */
  constructor (apiUrl) {
    this.apiUrl = apiUrl
    this.cache = null
  }

  callApi (method = 'GET', query = {}) {
    let url = this.apiUrl + '?'
    for (let [key, value] of Object.entries(query)) {
      url += key + '=' + value + '&'
    }
    url = url.slice(0, -1)

    return new Promise((resolve, reject) => {
      fetch(url, {
        method
      }).then(response => {
        if (!response.ok) {
          response.text().then(text => {
            throw new Error(text)
          }).catch(reject)
          return
        }
        response.json().then(json => {
          if (!json.ok) {
            reject(json.error)
            return
          }
          if (json.hasOwnProperty('payload')) {
            resolve(json.payload)
          } else {
            resolve()
          }
        }).catch(reject)
      }).catch(reject)
    })
  }

  /**
   * Retrieve the data and pass them to given callback function.
   * If the data are available in cache, the callback is invoked immediately (synchronously).
   * Otherwise the data are loaded from the REST API and cached internally.
   * @param {Function} callback Function which is called back once the data become available.
   *                     The callback receives the data (as array of objects, where each object
   *                     holds `id`, `caption`, and `hours` properties).
   *                     If the fetch failed, the callback is invoked with two arguments,
   *                     first one (data) is null, the second one is error message
   */
  getData (callback) {
    if (this.cache !== null) {
      callback(Object.values(this.cache))
      return
    }
    this.callApi().then(categories => {
      let completeCategories = []
      for (let category of categories) {
        this.callApi('GET', {
          action: 'hours',
          id: category.id
        }).then(completeCategory => {
          completeCategories.push(completeCategory)
          if (completeCategories.length === categories.length) {
            this.save(completeCategories)
            callback(completeCategories)
          }
        }).catch(reason => {
          callback(null, reason)
        })
      }
    }).catch(reason => {
      callback(null, reason)
    })
  }

  save (categories) {
    this.cache = {}
    for (const category of categories) {
      this.cache[category.id] = category
    }
  }

  /**
   * Invalidate internal cache. Next invocation of getData() will be forced to load data from the server.
   */
  invalidate () {
    this.cache = null
  }

  /**
   * Modify hours for one record.
   * @param {number} id ID of the record in question.
   * @param {number} hours New value of the hours (m)
   * @param {Function} callback Invoked when the operation is completed.
   *                            On failure, one argument with error message is passed to the callback.
   */
  setHours (id, hours, callback = null) {
    this.callApi('POST', {
      action: 'hours',
      id,
      hours
    }).then(() => {
      if (this.cache !== null) {
        this.cache[id].hours = hours
      }
      callback()
    }).catch(callback)
  }
}

// In nodejs, this is the way how export is performed.
// In browser, module has to be a global variable object.
module.exports = { DataModel }
